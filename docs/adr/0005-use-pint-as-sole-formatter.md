# ADR-0005: Use Pint as Sole Formatter

## Status

Accepted

## Context

Pint, Rector, and Mago can all modify code style. Conflicting auto-fixes cause CI churn and merge noise.

## Decision

Pint is the only formatter. Rector runs with `codingStyle: false`. Mago runs lint/analyze/guard in check mode only; `mago fmt` is not used in scripts or CI.

## Alternatives Considered

- **Mago as formatter** — would conflict with Laravel Pint preset already in use
- **Both Pint and Mago fmt** — guaranteed formatting fights

## Consequences

- Positive: Single source of truth for code style
- Negative: Mago lint rules may suggest fixes that Pint does not apply automatically
