<?php

return [
    // 默认跳转页面对应的模板文件
    'dispatch_success_tmpl' => \Initearth\think\JumpTemplate::default(),
    'dispatch_error_tmpl'   => \Initearth\think\JumpTemplate::default(),

    // success() 未指定地址且 Referer 不可用时的回退地址；留空表示不跳转
    'default_url' => '/',

    // Referer 白名单域名；为空时只接受同源 Referer
    'allowed_referer_hosts' => [],
];
