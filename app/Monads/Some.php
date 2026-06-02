<?php

declare(strict_types=1);

namespace App\Monads;

/**
 * @template-covariant T
 *
 * @implements Option<T>
 */
final readonly class Some implements Option
{
    /**
     * @param  T  $value
     */
    public function __construct(private mixed $value) {}

    /**
     * @template U
     *
     * @param  callable(T): U  $mapper
     * @return Some<U>
     */
    public function map(callable $mapper): Option
    {
        return new Some($mapper($this->value));
    }

    public function flatMap(callable $mapper): Option
    {
        return $mapper($this->value);
    }

    public function match(callable $some, callable $none): mixed
    {
        return $some($this->value);
    }

    public function unwrapOr(mixed $default): mixed
    {
        return $this->value;
    }

    public function isSome(): bool
    {
        return true;
    }

    public function isNone(): bool
    {
        return false;
    }

    public function value(): mixed
    {
        return $this->value;
    }
}
