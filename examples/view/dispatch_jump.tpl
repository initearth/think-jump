<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>跳转提示</title>
    <style>
        * { margin: 0; padding: 0; }
        body { background: #fff; color: #333; font: 16px/1.8 "Microsoft Yahei", Arial, sans-serif; }
        .jump-box { max-width: 640px; margin: 120px auto 0; padding: 0 24px; }
        .jump-box h1 { font-size: 40px; font-weight: normal; }
        .jump-box .msg { font-size: 20px; margin-top: 8px; }
        .jump-box .tip { margin-top: 16px; color: #666; }
        .jump-box a { color: #1677ff; text-decoration: none; }
    </style>
</head>
<body>
<div class="jump-box">
    {if $code == 1}
    <h1>操作成功</h1>
    {else /}
    <h1>操作失败</h1>
    {/if}
    <p class="msg">{$msg}</p>
    <p class="tip">页面将在 <span id="wait">{$wait}</span> 秒后自动跳转，<a id="href" href="{$url}"{if $back} data-back="1"{/if}>立即跳转</a></p>
</div>
<script>
(function () {
    var waitEl = document.getElementById('wait');
    var link = document.getElementById('href');
    var back = link.getAttribute('data-back') === '1';
    var remaining = parseInt(waitEl.textContent, 10);
    var timer;

    if (isNaN(remaining) || remaining < 0) {
        remaining = 0;
    }
    waitEl.textContent = remaining;

    function jump() {
        if (back) {
            history.back();
        } else {
            location.href = link.href;
        }
    }

    link.addEventListener('click', function (event) {
        event.preventDefault();
        clearInterval(timer);
        jump();
    });

    timer = setInterval(function () {
        remaining -= 1;
        waitEl.textContent = remaining;
        if (remaining <= 0) {
            clearInterval(timer);
            jump();
        }
    }, 1000);
})();
</script>
</body>
</html>
