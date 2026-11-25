# initearth/think-jump

适用于 ThinkPHP 8 的跳转扩展。通过 `Initearth\think\Jump` trait 为控制器提供 `success()`、`error()`、`result()`、`redirect()` 四个快捷方法，统一处理 HTML 页面跳转与 API 响应。

## 特性

- trait 接入，无需继承特定基类
- `success()` / `error()` 按请求类型自动选择跳转模板或 JSON
- `result()` 默认返回 JSON，数据结构统一
- 跳转地址自动解析，并过滤非 `http` / `https` 协议
- 模板变量在输出前完成 HTML 转义
- 模板路径可配置；配置缺失或路径失效时自动回退到内置模板

## 环境要求

| 依赖 | 版本 |
| --- | --- |
| PHP | >= 8.0 |
| topthink/framework | ^8.0 |
| topthink/think-view | ^2.0 |

## 安装

```bash
composer require initearth/think-jump
```

## 快速开始

在控制器中引入 trait：

```php
<?php

namespace app\controller;

use Initearth\think\Jump;

class Index
{
    use Jump;

    public function index()
    {
        return $this->success('操作成功', 'index/index');
    }
}
```

也可以在项目的 `BaseController` 中统一引入：

```php
<?php

namespace app;

use Initearth\think\Jump;

abstract class BaseController
{
    use Jump;

    // 其余初始化逻辑保持不变
}
```

`Jump` 内部通过 `$this->request ?? request()` 和 `$this->app ?? app()` 获取请求与应用实例，控制器无需声明 `$request`、`$app` 属性；即使已声明同名属性（包括类型化属性）也不会冲突。

## 响应方法

四个方法都会在内部抛出 `HttpResponseException`，由 ThinkPHP 统一接管响应，控制器中写 `return $this->xxx()` 即可，异常会中断后续逻辑。

### success()

操作成功跳转。普通 HTML 请求渲染模板，Ajax / JSON 请求返回 JSON。

```php
success(
    mixed $msg = '',
    ?string $url = null,
    mixed $data = '',
    int $wait = 3,
    array $header = []
)
```

| 参数 | 默认值 | 说明 |
| --- | --- | --- |
| `$msg` | `''` | 提示信息 |
| `$url` | `null` | 跳转地址；为空时取 `HTTP_REFERER` |
| `$data` | `''` | 返回的数据 |
| `$wait` | `3` | 跳转等待时间（秒） |
| `$header` | `[]` | 响应 Header |

```php
return $this->success('登录成功', 'index/index');
return $this->success('保存成功', 'index/index', ['id' => 1], 5);
```

### error()

操作失败跳转，签名与 `success()` 相同。未指定 `$url` 时：

- 普通 HTML 请求使用 `javascript:history.back(-1);` 返回上一页；
- Ajax 请求的 `url` 为空字符串。

```php
return $this->error('登录失败');
return $this->error('参数错误', 'login/index');
```

### result()

返回统一格式的 API 数据，默认 JSON。

```php
result(
    mixed $data,
    int $code = 0,
    mixed $msg = '',
    string $type = '',
    array $header = []
)
```

| 参数 | 默认值 | 说明 |
| --- | --- | --- |
| `$data` | 必填 | 要返回的数据 |
| `$code` | `0` | 业务状态码 |
| `$msg` | `''` | 提示信息 |
| `$type` | `''` | 响应格式，为空时默认 `json` |
| `$header` | `[]` | 响应 Header |

```php
return $this->result(['username' => 'initearth']);
return $this->result(['id' => 1], 0, 'ok', 'json');
```

返回结构：

```json
{
    "code": 0,
    "msg": "",
    "time": 1700000000,
    "data": {
        "username": "initearth"
    }
}
```

显式传入 `$type` 时，请确保 `$data` 能被该响应类型正确处理。

### redirect()

URL 重定向，默认 HTTP 状态码为 302，`$with` 通过 Session flash 隐式传参。

```php
redirect(string $url, int $code = 302, array $with = [])
```

```php
return $this->redirect('/admin/index/index');
return $this->redirect((string)url('index/index', ['name' => 'think']));
return $this->redirect('https://www.thinkphp.cn');
return $this->redirect('/admin/index/index', 301, ['from' => 'home']);
```

`url()` 返回 `think\route\Url` 对象，在 `declare(strict_types=1)` 的文件中需要显式转为字符串。

## 响应规则

`success()` 和 `error()` 通过 `getResponseType()` 判断响应类型：

| 请求类型 | 响应 |
| --- | --- |
| 普通 HTML | 渲染 `dispatch_success_tmpl` / `dispatch_error_tmpl` 指定的模板 |
| Ajax 或 JSON | 返回 JSON |

JSON 响应结构：

```json
{
    "code": 1,
    "msg": "操作成功",
    "data": {},
    "url": "index/index",
    "wait": 3
}
```

`success()` 的 `code` 为 `1`，`error()` 的 `code` 为 `0`。

## 跳转地址规则

- 包含 `://`、`/` 开头，或以 `?`、`#`、`./`、`../` 开头的地址按原样使用；
- 其他地址通过 `route->buildUrl()` 生成；
- `success()` 未指定地址时读取 `HTTP_REFERER`；
- 非 `http` / `https` 协议（如 `javascript:`）会被过滤为空字符串，避免危险跳转。`error()` 未指定地址时内部使用的 `javascript:history.back(-1);` 不受此限制。

## 跳转模板

内置模板为 `src/tpl/dispatch_jump.html`，使用 think-view 模板标签实现，默认后缀为 `html`。

执行以下命令可将配置发布到项目的 `config/jump.php`：

```bash
php think vendor:publish
```

配置内容：

```php
<?php

return [
    'dispatch_success_tmpl' => app()->getRootPath() . '/vendor/initearth/think-jump/src/tpl/dispatch_jump.html',
    'dispatch_error_tmpl'   => app()->getRootPath() . '/vendor/initearth/think-jump/src/tpl/dispatch_jump.html',
];
```

未发布配置，或配置中的路径不存在时，扩展会自动使用内置模板。自定义 `vendor-dir` 时需调整这里的路径。

### 自定义模板

将内置模板复制到项目中（例如 `app/view/dispatch_jump.html`），然后修改配置：

```php
return [
    'dispatch_success_tmpl' => app()->getRootPath() . '/app/view/dispatch_jump.html',
    'dispatch_error_tmpl'   => app()->getRootPath() . '/app/view/dispatch_jump.html',
];
```

模板变量：

| 变量 | 说明 |
| --- | --- |
| `$code` | `1` 表示成功，`0` 表示失败 |
| `$msg` | 提示信息，已转为字符串并完成 HTML 转义 |
| `$data` | 返回的数据，保持原始类型 |
| `$url` | 跳转地址，已完成 HTML 转义 |
| `$wait` | 等待时间（秒） |

`$msg` 和 `$url` 在传入模板前已做安全处理，直接输出即可，无需再次调用 `htmlspecialchars` 或 `raw` 过滤。

## 安全说明

- 外部传入的跳转地址会解析协议，仅允许 `http`、`https` 与相对地址；
- 模板中的 `$msg`、`$url` 统一经过 `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`；
- `$msg` 为数组或对象时会先转为 JSON 字符串，避免类型错误。

## 示例

`examples` 目录提供可直接复制的示例：

| 文件 | 说明 |
| --- | --- |
| `examples/BaseController.php` | 在基类中引入 `Jump` trait |
| `examples/controller/Demo.php` | 四个方法的用法 |
| `examples/controller/Api.php` | Ajax / JSON 接口场景 |
| `examples/config/jump.php` | 自定义模板配置 |
| `examples/view/dispatch_jump.tpl` | 自定义模板示例 |

默认路由下可访问 `/demo/successDemo`、`/demo/errorDemo`、`/demo/resultDemo`、`/demo/redirectDemo`，以及 `/api/successApi`、`/api/errorApi`、`/api/resultApi`。

## 注意事项

- 控制器方法不要命名为 `success`、`error`、`result`、`redirect`，否则会覆盖 trait 中的同名方法。
- 四个方法均以异常方式返回响应，不要在调用后继续执行输出逻辑。
- `result()` 的 `$type` 为空时默认返回 `json`。
- 模板后缀使用 `html`，与 think-view 默认 `view_suffix` 一致；自定义模板可放在任意路径，配置中写完整路径即可。

## 许可证

木兰宽松许可证，第2版（MulanPSL-2.0）
