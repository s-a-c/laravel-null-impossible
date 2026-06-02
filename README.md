# Laravel Null Impossible Starter Kit

Laravel 13 starter kit for the [Null Impossible Domain](docs/architecture/null-impossible-domain/README.md) architecture: custom `Option` / `Result` monads, team-aware auth, Sanctum and Reverb boundary examples, and a minimal Filament admin adapter.

## Requirements

- PHP **8.5+**
- [Composer](https://getcomposer.org/)
- [pnpm](https://pnpm.io/) 11+
- [Laravel Installer](https://laravel.com/docs/starter-kits) 5.28+

## Create a New Application

```bash
laravel new my-app --using=s-a-c/laravel-null-impossible --pnpm
cd my-app
composer run dev
```

Or from the repository before Packagist publication:

```bash
laravel new my-app --using=https://github.com/s-a-c/laravel-null-impossible --pnpm
```

The installer runs Composer `post-create-project-cmd` (app key, SQLite file, migrations). It does **not** seed default users. Register the first account through the app, or run `php artisan db:seed` in **local** only for a demo user.

## Setup (Existing Clone)

```bash
composer setup
```

This installs PHP dependencies, copies `.env`, generates a key, migrates, installs pnpm packages, and builds frontend assets.

## Quality Gates

| Profile | Command              | When to use                                                                  |
| ------- | -------------------- | ---------------------------------------------------------------------------- |
| Fast    | `composer test`      | Day-to-day app development after `laravel new`                               |
| Strict  | `composer ci:strict` | Maintaining the Null Impossible core (PHPStan, Mago, type/coverage/mutation) |

## What Is Included

- **Core:** `App\Monads`, `App\Domain`, `App\Application`, `App\Infrastructure`
- **Auth:** Fortify with teams (personal team, current team, invitations, slugged URLs)
- **API:** Sanctum token issuance via application `Result` workflows
- **Broadcasting:** Reverb with channel authorization at the boundary
- **Admin:** Filament panel with policy-based Team resource (no Shield/plugin bundle)

## What Is Not Included

- Private Composer repositories or premium dev tooling
- Horizon, Pulse, Telescope (add separately if needed)
- Filament Shield and optional Filament plugins

## Documentation

- Glossary: [CONTEXT.md](CONTEXT.md)
- Architecture: [docs/architecture/null-impossible-domain/](docs/architecture/null-impossible-domain/)
- ADRs: [docs/adr/](docs/adr/)

## Publishing

This repository is the canonical source for the `s-a-c/laravel-null-impossible` Packagist package. Submit the package at [packagist.org](https://packagist.org/) after pushing to GitHub.

## License

MIT
