<?php

declare(strict_types=1);

namespace Initearth\think\Tests\Support;

use Initearth\think\Jump;

final class JumpSubject
{
    use Jump;

    protected $app;

    protected $request;

    public function __construct($app = null, $request = null)
    {
        $this->app     = $app;
        $this->request = $request;
    }

    public function callSuccess(...$args)
    {
        return $this->success(...$args);
    }

    public function callError(...$args)
    {
        return $this->error(...$args);
    }

    public function callResult(...$args)
    {
        return $this->result(...$args);
    }

    public function callRedirect(...$args)
    {
        return $this->redirect(...$args);
    }

    public function callBuildJumpUrl(string $url): string
    {
        return $this->buildJumpUrl($url);
    }

    public function callFilterUrl(?string $url): ?string
    {
        return $this->filterUrl($url);
    }

    public function callGetTmpl(string $key): string
    {
        return $this->getTmpl($key);
    }

    public function callViewData(array $result): array
    {
        return $this->viewData($result);
    }

    public function callGetResponseType(): string
    {
        return $this->getResponseType();
    }
}
