# Current Project State

This page records architecture-relevant package and integration state for the `s-a-c/laravel-null-impossible` starter kit. `composer.json` and lockfiles remain the source of truth for exact versions.

## Runtime Baseline

| Area           | Current State                          | Architectural Impact                                              |
| -------------- | -------------------------------------- | ----------------------------------------------------------------- |
| PHP            | `php` constraint is `^8.5`             | Native `\|>` examples are valid for pure transforms.              |
| Laravel        | `laravel/framework` is a direct dependency | Framework APIs stay outside the null-impossible core.          |
| Livewire / Flux / Blaze | Installed              | UI delivery belongs in HTTP/Livewire or Filament surfaces.       |
| Vite+ / pnpm   | Frontend scripts run through `vp` and `pnpm` | Use `pnpm run build` for production assets.                  |

## Monadic Core

The production monad stack is first-party:

- `App\Monads\Option`
- `App\Monads\Result`
- `App\Monads\Unit`

Application code should not import transitive functional packages for domain workflows.

## API And Broadcasting

| Package / File        | Current State                     | Boundary Rule                                                                 |
| --------------------- | --------------------------------- | ----------------------------------------------------------------------------- |
| `laravel/sanctum`     | Installed                         | Token issuance belongs behind application use cases returning `Result`.       |
| `laravel/reverb`      | Installed                         | Channel authorization delegates to application code at `routes/channels.php`. |
| `laravel-echo` / `pusher-js` | Installed via pnpm           | Browser subscription code lives under frontend resources.                     |

## Filament Delivery Tier

The admin panel is registered through `App\Providers\Filament\AdminPanelProvider` with `filament/filament` and a Team resource example. No Shield or optional Filament plugins ship in the default kit.

## Quality Gates

| Profile | Command              | Purpose                                      |
| ------- | -------------------- | -------------------------------------------- |
| Fast    | `composer test`      | Lint check + Pest for generated app workflow |
| Strict  | `composer ci:strict` | Full Null Impossible maintainer gate         |

## Drift Rules

Update this page when package changes affect layer boundaries, delivery-tier behavior, quality gates, monadic boundary conversions, or runtime build/test commands.
