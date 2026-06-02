# ADR-0003: Use Custom Option and Result Monads

## Status

Accepted

## Context

Domain and application code must not use `?T`, `false`, or exceptions for expected outcomes.

## Decision

Implement `App\Monads\Option`, `Result`, and `Unit` as first-party types with `map`, `flatMap`, and `match`.

Keep this first-party core intentionally small. The extended monad catalog is architecture guidance for future decisions, not a commitment to implement every functional-programming abstraction in production code.

## Alternatives Considered

- **Nullable types** — rejected; violates null impossible rule
- **PHP 8.4+ union with null** — same problem
- **External functional packages** — rejected as architectural dependencies; prefer minimal first-party types and Laravel-specific boundary adapters. Transitive packages such as `graham-campbell/result-type` and `phpoption/phpoption` do not define this project's monad model.

## Consequences

- Positive: Explicit types enforceable by PHPStan and Pest
- Negative: Boilerplate for `Ok`, `Err`, `Some`, and `None` implementations
- Negative: Team must learn monadic APIs
