<?php

declare(strict_types=1);

namespace App\Monads;

/**
 * @implements Option<never>
 */
final class None implements Option
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        return self::$instance ??= new self;
    }

    public function map(callable $mapper): Option
    {
        return $this;
    }

    public function flatMap(callable $mapper): Option
    {
        return $this;
    }

    public function match(callable $some, callable $none): mixed
    {
        return $none();
    }

    public function unwrapOr(mixed $default): mixed
    {
        return $default;
    }

    public function isSome(): bool
    {
        return false;
    }

    public function isNone(): bool
    {
        return true;
    }
}
