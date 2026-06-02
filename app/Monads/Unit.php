<?php

declare(strict_types=1);

namespace App\Monads;

final class Unit
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        return self::$instance ??= new self;
    }
}
