<?php
/**
 * Created by PhpStorm.
 * User: initearth
 * Date: 2026/06/05
 * Time: 10:43
 */

namespace Initearth\think;

use JsonException;
use think\exception\HttpResponseException;
use think\facade\Route;
use think\Response;

trait Jump
{
    private static array $tmplCache = [];

    /**
     * 操作成功跳转的快捷方法
     * @access protected
     * @param mixed $msg 提示信息
     * @param ?string $url 跳转的URL地址
     * @param mixed $data 返回的数据
     * @param int $wait 跳转等待时间
     * @param array $header 发送的Header信息
     * @return void
     */
    protected function success(mixed $msg = '', ?string $url = null, mixed $data = '', int $wait = 3, array $header = [])
    {
        $back = false;
        if (is_null($url)) {
            $referer = $this->requestReferer();
            $url     = ('' !== $referer && $this->isAllowedReferer($referer)) ? $referer : $this->defaultJumpUrl();
        } elseif ($url) {
            $url = $this->filterUrl($url);
            if ('' !== $url) {
                $url = $this->buildJumpUrl($url);
            }
        }

        $wait   = max(0, $wait);
        $result = [
            'code' => 1,
            'msg' => $msg,
            'data' => $data,
            'url' => $url,
            'wait' => $wait,
        ];

        $type = $this->getResponseType();
        if ('html' == strtolower($type)) {
            /** @var \think\response\View $view */
            $view     = Response::create($this->getTmpl('dispatch_success_tmpl'), 'view');
            $response = $view->assign($this->viewData($result + ['back' => $back]))
                ->header($header);
        } else {
            $response = Response::create($result, $type)->header($header);
        }

        throw new HttpResponseException($response);
    }

    /**
     * 操作错误跳转的快捷方法
     * @access protected
     * @param mixed $msg 提示信息
     * @param ?string $url 跳转的URL地址
     * @param mixed $data 返回的数据
     * @param int $wait 跳转等待时间
     * @param array $header 发送的Header信息
     * @return void
     */
    protected function error(mixed $msg = '', ?string $url = null, mixed $data = '', int $wait = 3, array $header = [])
    {
        $back = false;
        if (is_null($url)) {
            $back = $this->getResponseType() === 'html';
            $url  = '';
        } elseif ($url) {
            $url = $this->filterUrl($url);
            if ('' !== $url) {
                $url = $this->buildJumpUrl($url);
            }
        }

        $wait   = max(0, $wait);
        $result = [
            'code' => 0,
            'msg' => $msg,
            'data' => $data,
            'url' => $url,
            'wait' => $wait,
        ];

        $type = $this->getResponseType();
        if ('html' == strtolower($type)) {
            /** @var \think\response\View $view */
            $view     = Response::create($this->getTmpl('dispatch_error_tmpl'), 'view');
            $response = $view->assign($this->viewData($result + ['back' => $back]))
                ->header($header);
        } else {
            $response = Response::create($result, $type)->header($header);
        }

        throw new HttpResponseException($response);
    }

    /**
     * 返回封装后的API数据到客户端
     * @access protected
     * @param mixed $data 要返回的数据
     * @param int $code 返回的code
     * @param mixed $msg 提示信息
     * @param string $type 返回数据格式
     * @param array $header 发送的Header信息
     * @return void
     */
    protected function result(mixed $data, int $code = 0, mixed $msg = '', string $type = '', array $header = [])
    {
        $result = [
            'code' => $code,
            'msg' => $msg,
            'time' => time(),
            'data' => $data,
        ];

        $type = strtolower($type ?: 'json');
        if (!in_array($type, ['json', 'jsonp', 'xml'], true)) {
            $type = 'json';
        }

        $response = Response::create($result, $type)->header($header);

        throw new HttpResponseException($response);
    }

    /**
     * URL重定向
     * @access protected
     * @param string $url 跳转的URL表达式
     * @param int $code http code
     * @param array $with 隐式传参
     * @param bool $safe 是否按跳转地址规则过滤
     * @return void
     */
    protected function redirect(string $url, int $code = 302, array $with = [], bool $safe = true)
    {
        if ($safe) {
            $url = $this->filterUrl($url) ?? '';
            if ('' !== $url) {
                $url = $this->buildJumpUrl($url);
            }
        }

        /** @var \think\response\Redirect $redirect */
        $redirect = Response::create($url, 'redirect');
        $response = $redirect->code($code)->with($with);

        throw new HttpResponseException($response);
    }

    /**
     * 获取当前的response 输出类型
     * @access protected
     * @return string
     */
    protected function getResponseType(): string
    {
        $request = $this->request ?? request();
        return $request->isJson() || $request->isAjax() ? 'json' : 'html';
    }

    /**
     * 读取 jump 配置
     */
    private function jumpConfig(string $key, mixed $default = null): mixed
    {
        return ($this->app ?? app())->config->get('jump.' . $key, $default);
    }

    /**
     * 读取并过滤 HTTP_REFERER
     */
    private function requestReferer(): string
    {
        $request = $this->request ?? request();
        $referer = $request->server('HTTP_REFERER', '');

        if (!is_string($referer)) {
            return '';
        }

        return $this->filterUrl($referer) ?? '';
    }

    /**
     * Referer 默认只允许同源；allowed_referer_hosts 可补充白名单域名
     */
    private function isAllowedReferer(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || '' === $host) {
            return false;
        }

        $host = strtolower($host);
        if ($host === strtolower(($this->request ?? request())->host(true))) {
            return true;
        }

        $allowed = $this->jumpConfig('allowed_referer_hosts', []);
        if (!is_array($allowed)) {
            return false;
        }

        foreach ($allowed as $item) {
            if (is_string($item) && $host === strtolower($item)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 无可用 Referer 时的回退地址，默认 /
     */
    private function defaultJumpUrl(): ?string
    {
        $default = $this->jumpConfig('default_url');
        if (null === $default) {
            $default = '/';
        }

        if (!is_string($default) || '' === $default) {
            return null;
        }

        $default = $this->filterUrl($default);

        return '' === $default ? null : $this->buildJumpUrl($default);
    }

    /**
     * 解析跳转地址，绝对路径与锚点、查询串等相对地址按原样使用
     */
    private function buildJumpUrl(string $url): string
    {
        if (str_contains($url, '://') || str_starts_with($url, '/') || str_starts_with($url, '?')
            || str_starts_with($url, '#') || str_starts_with($url, './') || str_starts_with($url, '../')) {
            return $url;
        }

        return (string)Route::buildUrl($url);
    }

    /**
     * 过滤非 http/https 协议与协议相对地址，避免危险跳转
     */
    private function filterUrl(?string $url): ?string
    {
        if (null === $url || '' === $url) {
            return $url;
        }

        if (preg_match('/[\x00-\x20\x7F]/', $url)) {
            return '';
        }

        if (str_starts_with($url, '//') || str_starts_with($url, '/\\') || str_starts_with($url, '\\')) {
            return '';
        }

        if (preg_match('/^([a-zA-Z][a-zA-Z0-9+.-]*):/', $url, $matches)
            && !in_array(strtolower($matches[1]), ['http', 'https'], true)) {
            return '';
        }

        return $url;
    }

    /**
     * 获取跳转模板，配置缺失或路径失效时回退到扩展内置模板
     */
    private function getTmpl(string $key): string
    {
        $tpl      = $this->jumpConfig($key);
        $cacheKey = $key . ':' . (is_string($tpl) ? $tpl : '');

        if (!array_key_exists($cacheKey, self::$tmplCache)) {
            self::$tmplCache[$cacheKey] = is_string($tpl) && is_file($tpl) ? $tpl : JumpTemplate::default();
        }

        return self::$tmplCache[$cacheKey];
    }

    /**
     * 模板变量预处理：msg/url 统一转为字符串并完成 HTML 转义
     */
    private function viewData(array $result): array
    {
        $msg = $result['msg'];
        if (is_array($msg) || is_object($msg) || is_resource($msg)) {
            try {
                $msg = json_encode($msg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $msg = '';
            }
        }

        $flags = ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5;
        $result['msg'] = htmlspecialchars((string)$msg, $flags, 'UTF-8');
        $result['url'] = htmlspecialchars((string)($result['url'] ?? ''), $flags, 'UTF-8');

        return $result;
    }
}
