<?php
/**
 * Created by PhpStorm.
 * User: initearth
 * Date: 2026/06/05
 * Time: 10:43
 */

namespace Initearth\think;

use think\exception\HttpResponseException;
use think\Response;

trait Jump
{
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
        if (is_null($url) && isset($_SERVER["HTTP_REFERER"])) {
            $url = $this->filterUrl($_SERVER["HTTP_REFERER"]);
        } elseif ($url) {
            $url = $this->filterUrl($this->buildJumpUrl($url));
        }

        $result = [
            'code' => 1,
            'msg' => $msg,
            'data' => $data,
            'url' => $url,
            'wait' => $wait,
        ];

        $type = $this->getResponseType();
        if ('html' == strtolower($type)) {
            $response = Response::create($this->getTmpl('dispatch_success_tmpl'), 'view')
                ->assign($this->viewData($result))
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
        if (is_null($url)) {
            $url = ($this->request ?? request())->isAjax() ? '' : 'javascript:history.back(-1);';
        } elseif ($url) {
            $url = $this->filterUrl($this->buildJumpUrl($url));
        }

        $result = [
            'code' => 0,
            'msg' => $msg,
            'data' => $data,
            'url' => $url,
            'wait' => $wait,
        ];

        $type = $this->getResponseType();
        if ('html' == strtolower($type)) {
            $response = Response::create($this->getTmpl('dispatch_error_tmpl'), 'view')
                ->assign($this->viewData($result))
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

        $type = $type ?: 'json';
        $response = Response::create($result, $type)->header($header);

        throw new HttpResponseException($response);
    }

    /**
     * URL重定向
     * @access protected
     * @param string $url 跳转的URL表达式
     * @param int $code http code
     * @param array $with 隐式传参
     * @return void
     */
    protected function redirect(string $url, int $code = 302, array $with = [])
    {
        $response = Response::create($url, 'redirect');

        $response->code($code)->with($with);

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
     * 解析跳转地址，绝对路径与锚点、查询串等相对地址按原样使用
     */
    private function buildJumpUrl(string $url): string
    {
        if (str_contains($url, '://') || str_starts_with($url, '/') || str_starts_with($url, '?')
            || str_starts_with($url, '#') || str_starts_with($url, './') || str_starts_with($url, '../')) {
            return $url;
        }

        return (string)($this->app ?? app())->route->buildUrl($url);
    }

    /**
     * 过滤非 http/https 协议，避免 javascript: 等危险地址进入跳转链接
     */
    private function filterUrl(?string $url): ?string
    {
        if (null === $url || '' === $url) {
            return $url;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (null !== $scheme && !in_array(strtolower((string)$scheme), ['http', 'https'], true)) {
            return '';
        }

        return $url;
    }

    /**
     * 获取跳转模板，配置缺失或路径失效时回退到扩展内置模板
     */
    private function getTmpl(string $key): string
    {
        $tpl = ($this->app ?? app())->config->get('jump.' . $key);

        return is_string($tpl) && is_file($tpl) ? $tpl : __DIR__ . '/tpl/dispatch_jump.html';
    }

    /**
     * 模板变量预处理：msg/url 统一转为字符串并完成 HTML 转义
     */
    private function viewData(array $result): array
    {
        $msg = $result['msg'];
        if (is_array($msg) || is_object($msg)) {
            $msg = json_encode($msg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        $result['msg'] = htmlspecialchars((string)$msg, ENT_QUOTES, 'UTF-8');
        $result['url'] = htmlspecialchars((string)($result['url'] ?? ''), ENT_QUOTES, 'UTF-8');

        return $result;
    }
}
