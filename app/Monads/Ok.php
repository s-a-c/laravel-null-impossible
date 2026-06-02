<?php

declare(strict_types=1);

namespace App\Monads;

/**
 * @template-covariant T
 *
 * @implements Result<T, never>
 */
final readonly class Ok implements Result
{
    /**
     * @param  T  $value
     */
    public function __construct(private mixed $value) {}

    /**
     * @template TValue
     *
     * @param  TValue  $value
     * @return Ok<TValue>
     */
    public static function of(mixed $value): Ok
    {
        return new Ok($value);
    }

    /**
     * @template U
     *
     * @param  callable(T): U  $mapper
     * @return Ok<U>
     */
    public function map(callable $mapper): Result
    {
        return new Ok($mapper($this->value));
    }

    public function flatMap(callable $mapper): Result
    {
        return $mapper($this->value);
    }

    public function match(callable $ok, callable $err): mixed
    {
        return $ok($this->value);
    }

    public function isOk(): bool
    {
        return true;
    }

    public function isErr(): bool
    {
        return false;
    }

    public function value(): mixed
    {
        return $this->value;
    }
}
