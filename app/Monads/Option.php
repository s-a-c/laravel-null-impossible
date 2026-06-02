<?php

declare(strict_types=1);

namespace App\Monads;

/**
 * @template-covariant T
 */
interface Option
{
    /**
     * @template U
     *
     * @param  callable(T): U  $mapper
     * @return Option<U>
     */
    public function map(callable $mapper): Option;

    /**
     * @template U
     *
     * @param  callable(T): Option<U>  $mapper
     * @return Option<U>
     */
    public function flatMap(callable $mapper): Option;

    /**
     * @template U
     *
     * @param  callable(T): U  $some
     * @param  callable(): U  $none
     * @return U
     */
    public function match(callable $some, callable $none): mixed;

    public function unwrapOr(mixed $default): mixed;

    public function isSome(): bool;

    public function isNone(): bool;
}
