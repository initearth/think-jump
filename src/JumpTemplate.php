<?php

declare(strict_types=1);

namespace Initearth\think;

final class JumpTemplate
{
    public static function default(): string
    {
        return __DIR__ . '/tpl/dispatch_jump.html';
    }
}
