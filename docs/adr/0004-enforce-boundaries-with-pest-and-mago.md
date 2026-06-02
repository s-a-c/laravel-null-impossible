# ADR-0004: Enforce Boundaries with Pest and Mago

## Status

Accepted

## Context

Architecture rules must not rely on convention alone. We need automated enforcement in CI.

## Decision

Use Pest architecture tests for namespace rules, strict types, and forbidden patterns. Use Mago guard for structural and perimeter (layer dependency) checks.

## Alternatives Considered

- **Pest only** — good for patterns; weaker on dependency graphs
- **Mago only** — strong perimeter; less expressive for custom token rules
- **Deptrac** — additional tool; overlap with Mago guard

## Consequences

- Positive: Dual enforcement catches different violation classes
- Negative: Two config surfaces to maintain (`tests/Architecture`, `mago.toml`)
