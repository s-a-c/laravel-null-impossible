# ADR-0007: Adopt Two-Tier Quality Gates for Integration Surfaces

## Status

Accepted

## Context

The null-impossible core currently passes strict gates: PHPStan max, 100% type coverage, 100% line coverage, and 100% scoped mutation. Installing platform integrations (Sanctum/Reverb/observability/Filament) introduces delivery adapters, panel scaffolding, provider bootstrapping, and published config that are architecture-critical but not suitable for full 100% mutation and coverage targets at the same granularity as the domain core.

## Decision

Use two gate tiers:

1. **Core Tier** (`App\Monads`, `App\Domain`, `App\Application`)
    - 100% type coverage
    - 100% line coverage
    - 100% scoped mutation
    - PHPStan max
    - Architecture rules

2. **Delivery Tier** (`App\Filament`, Filament providers, integration providers/config)
    - PHPStan max
    - Architecture rules
    - Targeted smoke/feature tests
    - Explicit exclusion from 100% line/mutation minimums

This is implemented through PHPUnit `<source><exclude>` for designated delivery directories, while keeping PHPStan and architecture checks broad across `app`, `config`, `database`, and `routes`.

## Alternatives Considered

- **Single-tier 100% everywhere** — strong in theory, impractical for vendor-driven scaffolding and dashboard glue code.
- **Core-only gates, ignore delivery tier** — too weak; integration regressions would slip through.
- **Baseline files for static analysis** — rejected by project policy.

## Consequences

- Positive: preserves strict guarantees where domain correctness matters most.
- Positive: keeps CI enforceable while integration surface grows.
- Negative: requires discipline to keep exclusions narrow and explicit.
- Negative: delivery-tier test strategy must be maintained intentionally (smoke tests + arch constraints).
