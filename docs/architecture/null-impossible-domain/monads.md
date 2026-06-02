# Monads

Custom monads live in `App\Monads`. Concrete variants are `final` and `readonly` where PHP allows. `None` and `Unit` use lazy singletons and cannot be `readonly` on PHP 8.5 because their static `$instance` properties are assigned after construction.

This page documents the implemented production API: `Option`, `Result`, and `Unit`. For the broader functional-programming catalog, including monads that are framework-represented, candidates, or reference-only, see [Extended Monad Catalog](monad-catalog.md).

## Option

Represents optional presence without `?T`.

```php
/** @template T */
interface Option
{
    /** @template U @param callable(T): U $fn @return Option<U> */
    public function map(callable $fn): Option;

    /** @template U @param callable(T): Option<U> $fn @return Option<U> */
    public function flatMap(callable $fn): Option;

    /** @param callable(T): U $some @param callable(): U $none */
    public function match(callable $some, callable $none): mixed;

    public function unwrapOr(mixed $default): mixed;
}
```

**Replace `?Team`:**

```php
// Before
public function personalTeam(): ?Team
{
    return $this->teams()->where('is_personal', true)->first();
}

// After (infrastructure)
public function findPersonalTeam(UserId $userId): Option
{
    $team = Team::query()->where('user_id', $userId)->where('is_personal', true)->first();

    return $team === null ? None::instance() : new Some($team);
}
```

## Result

Represents success or typed failure without exceptions or `false`.

```php
/** @template T @template E */
interface Result
{
    public function map(callable $fn): Result;
    public function flatMap(callable $fn): Result;
    public function match(callable $ok, callable $err): mixed;
}
```

**Replace `switchTeam(): bool`:**

```php
// Before
public function switchTeam(Team $team): bool
{
    if (! $this->belongsToTeam($team)) {
        return false;
    }
    // ...
    return true;
}

// After
public function switchTeam(SwitchTeamCommand $command): Result
{
    return $this->memberships
        ->findForUserAndTeam($command->userId, $command->teamId)
        ->flatMap(fn (Membership $m) => $this->persistCurrentTeam($command->userId, $m->teamId()));
}
```

## Unit

Zero-value success for operations with no meaningful return payload.

```php
final class Unit
{
    private static ?self $instance = null;

    private function __construct() {}

    public static function instance(): self
    {
        return self::$instance ??= new self;
    }
}
```

## Anti-Patterns

| Avoid                                      | Use instead                              |
| ------------------------------------------ | ---------------------------------------- |
| `?T` in domain/application                 | `Option<T>`                              |
| `false` for business failure               | `Result<T, E>` with `Err`                |
| `abort(403)` in use cases                  | `Err(TeamAccessDenied)` rendered in HTTP |
| `try/catch` for validation                 | `Result` from validator adapter          |
| Sentinel `null` in `map()` then `filter()` | `flatMap` returning `Option`             |

## Pipe-Friendly Helpers

Monads expose methods compatible with PHP 8.5 `|>` when the left side is the monad:

```php
$result = $command
    |> $this->validate(...)
    |> $this->resolveTeam(...)
    |> $this->authorize(...);
```

For collection-shaped data, prefer Laravel `Collection::pipe()` and `pipeThrough()`.
