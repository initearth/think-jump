<?php

declare(strict_types=1);

namespace Initearth\think\Tests;

use Initearth\think\JumpTemplate;
use Initearth\think\Tests\Support\JumpSubject;
use PHPUnit\Framework\TestCase;
use think\App;
use think\Config;
use think\Cookie;
use think\exception\HttpResponseException;
use think\Request;
use think\Response;
use think\response\Json;
use think\response\Redirect;
use think\response\View as ViewResponse;
use think\Session;
use think\View;

final class JumpTest extends TestCase
{
    private App $app;

    private JumpSubject $subject;

    private array $serverBackup;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serverBackup = $_SERVER;
        $this->resetServer();

        $this->app = new App(dirname(__DIR__));
        $config    = new Config();
        $config->set(['route' => [], 'jump' => []]);

        $this->app->instance('config', $config);
        $this->app->instance('request', Request::__make($this->app));
        $this->app->instance('route', $this->app->make('route'));

        $this->subject = new JumpSubject($this->app, $this->app->request);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;

        parent::tearDown();
    }

    public function testFilterUrlBlocksDangerousSchemes(): void
    {
        $urls = [
            'javascript:alert(1)',
            'JAVASCRIPT:alert(1)',
            'data:text/html;base64,PHNjcmlwdD4=',
            'vbscript:msgbox(1)',
            'java' . chr(9) . 'script:alert(1)',
            'java' . chr(10) . 'script:alert(1)',
        ];

        foreach ($urls as $url) {
            self::assertSame('', $this->subject->callFilterUrl($url), $url);
        }
    }

    public function testFilterUrlBlocksProtocolRelativeAndBackslashUrls(): void
    {
        foreach (['//evil.com', '/\\evil.com', '\\\\evil.com', '/ bad'] as $url) {
            self::assertSame('', $this->subject->callFilterUrl($url), $url);
        }
    }

    public function testFilterUrlAllowsHttpHttpsAndRelativeUrls(): void
    {
        $urls = [
            'https://example.com/a?b=1#c',
            'http://example.com',
            '/index/index',
            'index/index',
            '?a=1',
            '#top',
            './detail',
            '../home',
        ];

        foreach ($urls as $url) {
            self::assertSame($url, $this->subject->callFilterUrl($url), $url);
        }
    }

    public function testBuildJumpUrlUsesRouteFacade(): void
    {
        $expected = (string)$this->app->route->buildUrl('demo/index');

        self::assertSame($expected, $this->subject->callBuildJumpUrl('demo/index'));
        self::assertSame('https://www.thinkphp.cn', $this->subject->callBuildJumpUrl('https://www.thinkphp.cn'));
        self::assertSame('?a=1', $this->subject->callBuildJumpUrl('?a=1'));
    }

    public function testGetTmplFallsBackAndHonorsConfig(): void
    {
        self::assertSame(JumpTemplate::default(), $this->subject->callGetTmpl('dispatch_success_tmpl'));

        $this->app->config->set(['jump' => ['dispatch_success_tmpl' => '/path/not/found.html']]);
        self::assertSame(JumpTemplate::default(), $this->subject->callGetTmpl('dispatch_success_tmpl'));

        $this->app->config->set(['jump' => ['dispatch_success_tmpl' => __FILE__]]);
        self::assertSame(__FILE__, $this->subject->callGetTmpl('dispatch_success_tmpl'));
    }

    public function testViewDataEscapesAndReplacesInvalidUtf8(): void
    {
        $data = $this->subject->callViewData([
            'msg' => ['name' => '<b>&'],
            'url' => 'https://example.com/?a=1&b=2" onclick="x',
        ]);

        self::assertStringContainsString('&lt;b&gt;&amp;', $data['msg']);
        self::assertStringContainsString('&amp;', $data['url']);
        self::assertStringContainsString('&quot;', $data['url']);

        $invalid = $this->subject->callViewData(['msg' => "\x80", 'url' => null]);
        self::assertSame("\u{FFFD}", $invalid['msg']);
        self::assertSame('', $invalid['url']);
    }

    public function testViewDataHandlesJsonEncodeFailure(): void
    {
        $resource = fopen('php://memory', 'rb');

        try {
            $data = $this->subject->callViewData(['msg' => $resource]);
            self::assertSame('', $data['msg']);
        } finally {
            fclose($resource);
        }
    }

    public function testSuccessThrowsViewResponseWithEscapedVars(): void
    {
        $this->bindViewResponse();

        $response = $this->captureResponse(
            fn () => $this->subject->callSuccess('<b>ok</b>', 'demo/index', ['id' => 1], 5, ['X-Test' => 'yes'])
        );

        self::assertInstanceOf(ViewResponse::class, $response);
        self::assertSame(JumpTemplate::default(), $response->getData());
        self::assertSame('yes', $response->getHeader('X-Test'));

        $vars = $response->getVars();
        self::assertSame(1, $vars['code']);
        self::assertSame('&lt;b&gt;ok&lt;/b&gt;', $vars['msg']);
        self::assertSame(['id' => 1], $vars['data']);
        self::assertSame(5, $vars['wait']);
        self::assertFalse($vars['back']);
    }

    public function testSuccessReturnsJsonForJsonRequest(): void
    {
        $this->refreshRequest(['HTTP_ACCEPT' => 'application/json']);

        $response = $this->captureResponse(
            fn () => $this->subject->callSuccess('ok', 'demo/index', ['id' => 1])
        );

        self::assertInstanceOf(Json::class, $response);
        $data = $response->getData();
        self::assertSame(1, $data['code']);
        self::assertSame('ok', $data['msg']);
        self::assertSame(['id' => 1], $data['data']);
        self::assertStringContainsString('demo/index', $data['url']);
    }

    public function testSuccessUsesSameHostReferer(): void
    {
        $this->refreshRequest([
            'HTTP_ACCEPT'  => 'application/json',
            'HTTP_REFERER' => 'https://localhost/from-page',
        ]);

        $response = $this->captureResponse(fn () => $this->subject->callSuccess());

        self::assertSame('https://localhost/from-page', $response->getData()['url']);
    }

    public function testSuccessRejectsExternalRefererAndFallsBack(): void
    {
        $this->refreshRequest([
            'HTTP_ACCEPT'  => 'application/json',
            'HTTP_REFERER' => 'https://evil.com/from-page',
        ]);

        $response = $this->captureResponse(fn () => $this->subject->callSuccess());

        self::assertSame('/', $response->getData()['url']);
    }

    public function testSuccessAllowsConfiguredRefererHost(): void
    {
        $this->app->config->set(['jump' => ['allowed_referer_hosts' => ['evil.com']]]);
        $this->refreshRequest([
            'HTTP_ACCEPT'  => 'application/json',
            'HTTP_REFERER' => 'https://evil.com/from-page',
        ]);

        $response = $this->captureResponse(fn () => $this->subject->callSuccess());

        self::assertSame('https://evil.com/from-page', $response->getData()['url']);
    }

    public function testSuccessFallsBackToConfiguredDefaultUrl(): void
    {
        $this->app->config->set(['jump' => ['default_url' => 'home/index']]);
        $this->refreshRequest(['HTTP_ACCEPT' => 'application/json']);

        $response = $this->captureResponse(fn () => $this->subject->callSuccess());

        self::assertStringContainsString('home/index', $response->getData()['url']);
    }

    public function testSuccessClampsNegativeWait(): void
    {
        $this->refreshRequest(['HTTP_ACCEPT' => 'application/json']);

        $response = $this->captureResponse(
            fn () => $this->subject->callSuccess('ok', 'demo/index', '', -5)
        );

        self::assertSame(0, $response->getData()['wait']);
    }

    public function testErrorDefaultUrlDependsOnResponseType(): void
    {
        $this->refreshRequest(['HTTP_ACCEPT' => 'application/json']);
        $json = $this->captureResponse(fn () => $this->subject->callError('bad'));
        self::assertSame('', $json->getData()['url']);
        self::assertArrayNotHasKey('back', $json->getData());

        $this->refreshRequest([]);
        $this->bindViewResponse();
        $view = $this->captureResponse(fn () => $this->subject->callError('bad'));
        self::assertSame('', $view->getVars()['url']);
        self::assertTrue($view->getVars()['back']);
    }

    public function testErrorFiltersExplicitDangerousUrl(): void
    {
        $this->refreshRequest(['HTTP_ACCEPT' => 'application/json']);

        $response = $this->captureResponse(
            fn () => $this->subject->callError('bad', 'javascript:alert(1)')
        );

        self::assertSame('', $response->getData()['url']);
    }

    public function testResultReturnsJsonEnvelope(): void
    {
        $response = $this->captureResponse(
            fn () => $this->subject->callResult(['id' => 1], 0, 'ok', '', ['X-Test' => 'yes'])
        );

        self::assertInstanceOf(Json::class, $response);
        self::assertSame('yes', $response->getHeader('X-Test'));

        $data = $response->getData();
        self::assertSame(0, $data['code']);
        self::assertSame('ok', $data['msg']);
        self::assertSame(['id' => 1], $data['data']);
        self::assertIsInt($data['time']);
    }

    public function testResultFallsBackForUnsupportedType(): void
    {
        $response = $this->captureResponse(
            fn () => $this->subject->callResult([], 0, '', 'bogus')
        );

        self::assertInstanceOf(Json::class, $response);
    }

    public function testRedirectSetsCodeAndSessionFlash(): void
    {
        $session = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->addMethods(['flash'])
            ->getMock();
        $session->expects(self::once())->method('flash')->with('from', 'home');

        $this->bindRedirectResponse($session);

        $response = $this->captureResponse(
            fn () => $this->subject->callRedirect('/demo/index', 301, ['from' => 'home'])
        );

        self::assertInstanceOf(Redirect::class, $response);
        self::assertSame(301, $response->getCode());
        self::assertSame('/demo/index', $response->getData());
    }

    public function testRedirectFiltersDangerousUrlByDefault(): void
    {
        $this->bindRedirectResponse();

        $filtered = $this->captureResponse(
            fn () => $this->subject->callRedirect('javascript:alert(1)')
        );
        self::assertSame('', $filtered->getData());

        $raw = $this->captureResponse(
            fn () => $this->subject->callRedirect('javascript:alert(1)', 302, [], false)
        );
        self::assertSame('javascript:alert(1)', $raw->getData());
    }

    public function testTemplatesUseExplicitBackFlag(): void
    {
        $files = [
            JumpTemplate::default(),
            dirname(__DIR__) . '/examples/view/dispatch_jump.tpl',
        ];

        foreach ($files as $file) {
            $content = (string)file_get_contents($file);
            self::assertStringContainsString('data-back', $content, $file);
            self::assertStringNotContainsString('javascript:history.back', $content, $file);
        }
    }

    public function testGetResponseTypeRecognizesJsonAndAjax(): void
    {
        $this->refreshRequest(['HTTP_ACCEPT' => 'application/json']);
        self::assertSame('json', $this->subject->callGetResponseType());

        $this->refreshRequest(['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        self::assertSame('json', $this->subject->callGetResponseType());

        $this->refreshRequest([]);
        self::assertSame('html', $this->subject->callGetResponseType());
    }

    private function captureResponse(callable $callback): Response
    {
        try {
            $callback();
        } catch (HttpResponseException $exception) {
            return $exception->getResponse();
        }

        self::fail('未抛出 HttpResponseException');
    }

    private function bindViewResponse(): void
    {
        $view = $this->createMock(View::class);
        $view->method('fetch')->willReturn('rendered');

        $this->app->instance('view', $view);
        $this->app->instance('cookie', $this->createMock(Cookie::class));
    }

    private function bindRedirectResponse(?Session $session = null): void
    {
        $this->app->instance('session', $session ?? $this->createMock(Session::class));
        $this->app->instance('cookie', $this->createMock(Cookie::class));
    }

    private function refreshRequest(array $server): void
    {
        $this->resetServer($server);

        $request = Request::__make($this->app);
        $this->app->instance('request', $request);
        $this->subject = new JumpSubject($this->app, $request);
    }

    private function resetServer(array $extra = []): void
    {
        $_SERVER = array_merge([
            'HTTP_HOST'       => 'localhost',
            'REQUEST_URI'     => '/',
            'REQUEST_METHOD'  => 'GET',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
        ], $extra);
    }
}
