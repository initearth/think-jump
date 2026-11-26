# initearth/think-jump

适用于 ThinkPHP 8 的跳转扩展。通过 `Initearth\think\Jump` trait 为控制器提供 `success()`、`error()`、`result()`、`redirect()` 四个快捷方法，统一处理 HTML 页面跳转与 API 响应。

## 特性

- trait 接入，无需继承特定基类
- `success()` / `error()` 按请求类型自动选择跳转模板或 JSON
- `result()` 默认返回 JSON，数据结构统一
- 跳转地址通过 `think\facade\Route` 解析，并过滤危险协议、协议相对地址与控制字符
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

`Jump` 内部通过 `$this->request ?? request()` 获取请求实例、通过 `$this->app ?? app()` 读取配置，跳转地址使用 `\think\facade\Route::buildUrl()` 生成。控制器无需声明 `$request`、`$app` 属性；即使已声明同名属性（包括类型化属性）也不会冲突。

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
| `$url` | `null` | 跳转地址；为空时读取 `HTTP_REFERER`，不可用时回退 `jump.default_url` |
| `$data` | `''` | 返回的数据 |
| `$wait` | `3` | 跳转等待时间（秒，负数按 `0` 处理） |
| `$header` | `[]` | 响应 Header |

```php
return $this->success('登录成功', 'index/index');
return $this->success('保存成功', 'index/index', ['id' => 1], 5);
```

### error()

操作失败跳转，签名与 `success()` 相同。未指定 `$url` 时：

- 普通 HTML 请求设置模板变量 `$back = true`，模板直接调用 `history.back()` 返回上一页，`$url` 保持为空字符串；
- JSON / Ajax 请求的 `url` 为空字符串，且不包含 `$back` 字段。

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
| `$type` | `''` | 响应格式，支持 `json`、`jsonp`、`xml`；为空或不支持时回退 `json` |
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

显式传入 `$type` 时仅支持 `json`、`jsonp`、`xml`，其他值会回退为 `json`；请确保 `$data` 能被该响应类型正确处理。

### redirect()

URL 重定向，默认 HTTP 状态码为 302，`$with` 通过 Session flash 隐式传参。`$safe` 为 `true` 时按跳转地址规则过滤并生成 URL；确认 URL 可信时可传 `false` 保留原值。

```php
redirect(string $url, int $code = 302, array $with = [], bool $safe = true)
```

```php
return $this->redirect('/admin/index/index');
return $this->redirect((string)url('index/index', ['name' => 'think']));
return $this->redirect('https://www.thinkphp.cn');
return $this->redirect('/admin/index/index', 301, ['from' => 'home']);
return $this->redirect($trustedUrl, 302, [], false);
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
- 其他地址通过 `\think\facade\Route::buildUrl()` 生成，扩展内部会将其返回的 `Url` 对象强制转为字符串；
- `success()` 未指定地址时读取 `HTTP_REFERER`，默认只接受同源地址；`jump.allowed_referer_hosts` 可补充白名单域名；
- Referer 不可用或不在白名单时回退到 `jump.default_url`，默认为 `/`；配置为 `''` 时不跳转；
- 非 `http` / `https` 协议（如 `javascript:`）、`//` / `/\` / `\` 开头的协议相对地址，以及包含控制字符的地址会被过滤为空字符串。

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
    'dispatch_success_tmpl' => \Initearth\think\JumpTemplate::default(),
    'dispatch_error_tmpl'   => \Initearth\think\JumpTemplate::default(),

    // success() 未指定地址且 Referer 不可用时的回退地址；留空表示不跳转
    'default_url' => '/',

    // Referer 白名单域名；为空时只接受同源 Referer
    'allowed_referer_hosts' => [],
];
```

未发布配置，或配置中的路径不存在时，扩展会自动使用内置模板。发布配置通过 `JumpTemplate::default()` 引用包内模板，自定义 `vendor-dir` 无需调整；未发布配置时 `default_url` 默认 `/`，Referer 默认只允许同源。

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
| `$data` | 返回的数据，保持原始类型，输出时需自行转义 |
| `$url` | 跳转地址，已完成 HTML 转义，始终是真实 URL 或空字符串 |
| `$wait` | 等待时间（秒，已限制为非负整数） |
| `$back` | `true` 表示返回上一页；模板应据此调用 `history.back()`，不要读取特殊协议 URL |

`$msg` 和 `$url` 在传入模板前已做安全处理，直接输出即可，无需再次调用 `htmlspecialchars` 或 `raw` 过滤。自定义模板复制内置模板后，保留 `data-back` 分支即可兼容 `$back`。

## 安全说明

- 外部传入的跳转地址仅允许 `http`、`https` 与相对地址；`//`、`/\`、`\` 开头及含控制字符的地址会被拒绝；
- `HTTP_REFERER` 默认只接受同源，其他域名需通过 `jump.allowed_referer_hosts` 显式加入白名单；显式传入的绝对 URL 仍由调用方负责域名策略；
- 模板中的 `$msg`、`$url` 统一经过 `htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8')`，无效 UTF-8 字节会被替换为 `U+FFFD`；
- `$msg` 为数组、对象或资源时会先转为 JSON 字符串，编码失败时回退为空字符串；`$data` 保持原始类型，自定义模板直接输出时需自行转义；
- 返回上一页通过 `$back` 标志实现，模板直接调用 `history.back()`，`$url` 中不会再出现 `javascript:` 伪协议。

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
- `result()` 的 `$type` 仅支持 `json`、`jsonp`、`xml`；为空或传入其他值时回退为 `json`。
- 模板后缀使用 `html`，与 think-view 默认 `view_suffix` 一致；自定义模板可放在任意路径，配置中写完整路径即可。

## 开发

```bash
composer install
composer test
composer check
```

`composer test` 运行 PHPUnit；`composer check` 先校验 `composer.json` 再运行测试。CI 会在 PHP 8.0 至 8.4 上执行同一套检查。

## 许可证

木兰宽松许可证，第2版（MulanPSL-2.0），全文见 `LICENSE`。
