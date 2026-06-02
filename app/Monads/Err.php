<?php

declare(strict_types=1);

namespace App\Monads;

/**
 * @template-covariant E
 *
 * @implements Result<never, E>
 */
final readonly class Err implements Result
{
    /**
     * @param  E  $error
     */
    public function __construct(private mixed $error) {}

    /**
     * @template TError
     *
     * @param  TError  $error
     * @return Err<TError>
     */
    public static function of(mixed $error): Err
    {
        return new Err($error);
    }

    public function map(callable $mapper): Result
    {
        return $this;
    }

    public function flatMap(callable $mapper): Result
    {
        return $this;
    }

    public function match(callable $ok, callable $err): mixed
    {
        return $err($this->error);
    }

    public function isOk(): bool
    {
        return false;
    }

    public function isErr(): bool
    {
        return true;
    }

    public function error(): mixed
    {
        return $this->error;
    }
}
