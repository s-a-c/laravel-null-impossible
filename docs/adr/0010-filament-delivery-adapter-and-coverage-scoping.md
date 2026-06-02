# ADR 0010: Filament Delivery Adapter And Coverage Scoping

## Status

Accepted

## Context

Filament and related plugins are required for admin delivery features. These files are framework-heavy and not part of the null-impossible core.

## Decision

- Install Filament ecosystem packages and Shield for panel authorization.
- Scaffold an admin panel and a reference `TeamResource` under `App\\Filament`.
- Keep Filament as a delivery adapter layer.
- Exclude delivery adapter files from strict 100% line coverage gates in `phpunit.xml`:
    - `app/Filament/**`
    - dashboard/observability adapter providers
    - `app/Policies/RolePolicy.php`

## Consequences

- Core tier remains strict (100% type/line/mutation) and architecture-focused.
- Delivery tier is still enforced by PHPStan, Rector, Mago, and architecture tests.
- Admin UX can evolve without polluting core-domain quality signals.
