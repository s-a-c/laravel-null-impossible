# Pipelines and Pipes

Three composition tools, each with a distinct role.

## Laravel Collections

Use for collection-shaped transformations with domain vocabulary: `map`, `filter`, `flatMap`, `pipe`, `pipeThrough`, `when`, `unless`.

```php
return $this->teams()
    ->get()
    ->reject(fn (Team $team) => $this->isCurrentTeam($team))
    ->map(fn (Team $team) => $this->toUserTeam($team))
    ->values();
```

Prefer Collection methods over nested function calls when the operations are semantically rich.

## Laravel Pipeline

Use for DI-backed staged workflows where each stage is an invokable class.

```php
return Pipeline::send($command)
    ->through([
        ValidateTeamCommand::class,
        ResolveTeamMembership::class,
        AuthorizeTeamAccess::class,
    ])
    ->thenReturn();
```

Pipeline stages return `Result` and short-circuit by returning `Err` — the next stage checks with `match()` before proceeding.

## PHP 8.5 Native `|>`

Use for pure single-argument transformation chains. The callable on the right must accept exactly one required parameter.

```php
$slug = $rawName
    |> trim(...)
    |> (fn (string $s) => Str::slug($s))
    |> (fn (string $s) => TeamSlug::fromString($s));
```

**When to use `|>`:** value transforms with no DI, no side effects, no branching.

**When not to use `|>`:** persistence, authorization, HTTP, Filament actions — use Pipeline or explicit use case methods instead.

## Decision Guide

| Situation | Tool |
|-----------|------|
| Transform a list | Collection |
| Multi-step use case with DI | Laravel Pipeline |
| Pure string/value normalization | `\|>` |
| Optional chaining in domain | `Option::flatMap` |
| Success/failure branching | `Result::match` |
