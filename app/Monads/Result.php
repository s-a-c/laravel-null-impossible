<?php

declare(strict_types=1);

namespace App\Monads;

/**
 * @template-covariant T
 * @template-covariant E
 */
interface Result
{
    /**
     * @template U
     *
     * @param  callable(T): U  $mapper
     * @return Result<U, E>
     */
    public function map(callable $mapper): Result;

    /**
     * @template U
     * @template F
     *
     * @param  callable(T): Result<U, F>  $mapper
     * @return Result<U, E|F>
     */
    public function flatMap(callable $mapper): Result;

    /**
     * @template U
     *
     * @param  callable(T): U  $ok
     * @param  callable(E): U  $err
     * @return U
     */
    public function match(callable $ok, callable $err): mixed;

    public function isOk(): bool;

    public function isErr(): bool;
}
