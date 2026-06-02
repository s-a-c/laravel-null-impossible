# ADR-0011: Dedicated Public Starter Kit Repository

## Status

Accepted

## Context

The Null Impossible architecture was incubated in a working repository that also contained agent tooling, private Composer repositories, premium dev dependencies, generated Filament assets, and R&D documentation. That shape is unsuitable for `laravel new --using=vendor/package` consumers, who must install without credentials or manual cleanup.

## Decision

Publish the starter kit from a dedicated public repository and Packagist package named `s-a-c/laravel-null-impossible`. The package is credential-free: no private Composer repositories, no premium dev tools in the install path, and no committed generated vendor frontend assets.

The default generated application includes:

- Null Impossible core tiers (`App\Monads`, `App\Domain`, `App\Application`, `App\Infrastructure`)
- Fortify authentication and the full team model
- Sanctum API token issuance and Reverb channel authorization as boundary examples
- A minimal Filament admin panel with policy-based access and a Team resource example

Heavy observability stacks, Filament Shield, and optional Filament plugins are excluded from the default install.

## Alternatives Considered

- **Clean the incubation repository in place** — couples public consumers to local agent and planning artifacts
- **Export branch from incubation repo** — adds release-step drift risk
- **Document private credentials for install** — blocks credential-free `composer create-project`

## Consequences

- Positive: Clear product boundary for `laravel new --using=s-a-c/laravel-null-impossible`
- Positive: Install works without `auth.json` or Flux Satis credentials
- Negative: Two repositories to maintain until incubation history is fully merged or archived
- Negative: PHP `^8.5` limits hosting compared with official Laravel starter kits on `^8.3`
