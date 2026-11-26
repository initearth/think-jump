<?php

return [
    'dispatch_success_tmpl' => app()->getRootPath() . '/examples/view/dispatch_jump.tpl',
    'dispatch_error_tmpl'   => app()->getRootPath() . '/examples/view/dispatch_jump.tpl',

    // success() 无可用 Referer 时的回退地址；留空表示不跳转
    'default_url' => '/',

    // Referer 白名单域名；为空时只接受同源 Referer
    'allowed_referer_hosts' => [],
];
