# ADR 0009: Observability Stack With Admin-Gated Access

## Status

Accepted

## Context

The system requires production diagnostics (queues, traces, metrics) without leaking operational dashboards to non-admin users.

## Decision

- Install and configure:
    - `laravel/horizon`
    - `laravel/pulse`
    - `laravel/telescope`
    - `laravel/ai`, `livewire/blaze`, `nunomaduro/essentials`, `nunomaduro/laravel-optimize-database`
- Define explicit dashboard gates:
    - `viewHorizon`
    - `viewPulse`
    - `viewTelescope`
- Add `config/observability.php` with `observability.admin_email` as the single access control value.

## Consequences

- Dashboards are available in local development and restricted by admin email in non-local environments.
- Operational tooling remains in delivery adapters, not in core domain/application logic.
- Queue and metrics integrations are enabled without weakening null-impossible boundaries.
