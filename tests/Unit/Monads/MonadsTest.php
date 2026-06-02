<?php

declare(strict_types=1);

use App\Monads\Err;
use App\Monads\None;
use App\Monads\Ok;
use App\Monads\Some;
use App\Monads\Unit;

covers([
    Err::class,
    None::class,
    Ok::class,
    Some::class,
    Unit::class,
]);

describe('Option', function (): void {
    it('creates and unwraps some values', function (): void {
        $option = new Some('value');

        expect($option->isSome())->toBeTrue()
            ->and($option->isNone())->toBeFalse()
            ->and($option->value())->toBe('value')
            ->and($option->unwrapOr('fallback'))->toBe('value');
    });

    it('maps some values', function (): void {
        $option = new Some(2);

        expect($option->map(fn (int $n): int => $n * 3)->unwrapOr(0))->toBe(6);
    });

    it('skips map for none', function (): void {
        $option = None::instance();

        expect($option->map(fn (int $n): int => $n * 3)->isNone())->toBeTrue()
            ->and($option->flatMap(fn (int $n): Some => new Some($n * 3))->isNone())->toBeTrue()
            ->and($option->unwrapOr('fallback'))->toBe('fallback')
            ->and($option->isSome())->toBeFalse()
            ->and(None::instance())->toBe($option);
    });

    it('matches some and none', function (): void {
        expect(new Some('a')->match(fn (string $v): string => $v, fn (): string => 'missing'))->toBe('a');
        expect(None::instance()->match(fn (string $v): string => $v, fn (): string => 'missing'))->toBe('missing');
    });

    it('flatMaps to nested options', function (): void {
        $some = new Some(1)->flatMap(fn (int $n): Some => new Some($n + 1));
        $none = new Some(0)->flatMap(fn (): None => None::instance());

        expect($some->unwrapOr(0))->toBe(2);
        expect($none->isNone())->toBeTrue();
    });
});

describe('Result', function (): void {
    it('creates ok values and exposes state', function (): void {
        $result = Ok::of('value');

        expect($result->isOk())->toBeTrue()
            ->and($result->isErr())->toBeFalse()
            ->and($result->value())->toBe('value');
    });

    it('maps ok values', function (): void {
        $result = new Ok(2)->map(fn (int $n): int => $n + 1);

        expect($result->match(fn (int $v): int => $v, fn (): int => 0))->toBe(3);
    });

    it('preserves err on map', function (): void {
        $error = 'denied';
        $err = new Err($error);
        $result = $err->map(fn (int $n): int => $n + 1);
        $flatMapped = $result->flatMap(fn (): Ok => new Ok('unused'));

        expect($result->isErr())->toBeTrue()
            ->and($result->isOk())->toBeFalse()
            ->and($flatMapped)->toBeInstanceOf(Err::class)
            ->and($err->error())->toBe('denied');
        expect($result->match(fn (): string => 'ok', fn (string $e): string => $e))->toBe('denied');
    });

    it('flatMaps results', function (): void {
        $ok = new Ok(1)->flatMap(fn (int $n): Ok => new Ok($n + 1));
        $err = new Ok(0)->flatMap(fn (): Err => new Err('fail'));

        expect($ok->match(fn (int $v): int => $v, fn (): int => -1))->toBe(2);
        expect($err->isErr())->toBeTrue();
    });
});

describe('Unit', function (): void {
    it('is a singleton', function (): void {
        expect(Unit::instance())->toBe(Unit::instance());
    });
});
