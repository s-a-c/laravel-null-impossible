# Testing and Tooling

## Tool Ownership

| Tool             | Role                                        | Fix mode                      | CI mode                 |
| ---------------- | ------------------------------------------- | ----------------------------- | ----------------------- |
| Pint             | Sole formatter                              | `composer lint`               | `composer lint:check`   |
| Rector           | Semantic refactors                          | `composer rector` (then Pint) | `composer rector:check` |
| PHPStan/Larastan | Static analysis                             | —                             | `composer analyse`      |
| Mago             | Lint + guard perimeter                      | check only                    | `composer mago:check`   |
| Pest             | Tests, architecture, type/coverage/mutation | —                             | via `composer ci:check` |
| Vite+ / pnpm     | Frontend build pipeline                     | `pnpm run dev`                | `pnpm run build`        |

**Conflict prevention:** Rector never enables `codingStyle`. Mago formatter is not used. Only Pint mutates formatting.

## Composer Scripts

```bash
composer lint          # fix formatting
composer lint:check    # verify formatting
composer rector        # refactor + format
composer rector:check  # dry-run refactor
composer analyse       # PHPStan max
composer mago:check    # mago lint + guard perimeter
composer test:unit     # Pest/PHPUnit
composer test:type     # 100% type coverage
composer test:coverage # 100% line coverage
composer test:mutation # 100% scoped mutation
composer test          # fast gate (lint + tests)
composer ci:strict     # full maintainer gate
```

## Frontend Scripts

```bash
pnpm run build # Vite+ production build
pnpm run dev   # Vite+ development server
```

This repo uses pnpm and Vite+. Do not use npm or yarn for project installs or scripts.

## Two-Tier Quality Gate Model

### Core Tier (Null-Impossible Core)

Scope:

- `App\\Monads`
- `App\\Domain`
- `App\\Application`

Required gates:

- `pest --type-coverage --min=100`
- `php artisan test --coverage --min=100` (against configured source include/exclude)
- `pest --mutate --class=App\\Monads --class=App\\Domain --class=App\\Application --min=100`
- Architecture tests + PHPStan max + Mago perimeter

### Delivery Tier (Integration/Adapter Surfaces)

Scope examples:

- `App\\Filament`
- `App\\Providers\\Filament`
- Integration providers/config/dashboard adapters

Required gates:

- PHPStan max
- Architecture tests
- Targeted smoke/feature tests

Delivery-tier paths may be excluded from strict 100% line/mutation minimums via `phpunit.xml` source excludes, but are never excluded from PHPStan or architecture checks.

## Pest Gates

- **Architecture tests** — layer dependencies, strict types, no try/catch in app, no abort outside boundary adapters
- **Type coverage** — `pest --type-coverage --min=100`
- **Line coverage** — `php artisan test --coverage --min=100`
- **Mutation** — scoped to core via `composer test:mutation`

## PHPStan

- Level: `max`
- Includes: bleedingEdge + auto-discovered Larastan/strict-rules integrations
- PHP version: 8.5
- **No baseline files** — violations are fixed in code
- `ergebnis/phpstan-rules` remains intentionally uninstalled because it conflicts with Laravel/Filament extension patterns in this codebase.

## Mago Guard

- Structural lint checks
- Perimeter rules for layer boundaries (core null-impossible scope)

## Why No Baselines

Baselines hide architectural drift. This project fixes violations at the source so gates remain trustworthy for humans and agents.

## Current Delivery-Tier Coverage Excludes

Configured in `phpunit.xml` source excludes:

- `app/Filament/**`
  These files remain in PHPStan, Rector, Mago, and architecture checks.
