# Migration Guide

Migrate incrementally without PHPStan or Pest baselines. Each vertical slice should leave the app runnable.

## Order of Migration

1. **Monads** — `App\Monads` with unit tests
2. **Domain value objects** — team slug, user id, membership role
3. **Infrastructure repositories** — wrap Eloquent `first()` → `Option`
4. **Application use cases** — one workflow at a time
5. **HTTP/Filament boundaries** — render `Result` last
6. **Retire nullable trait methods** — replace `HasTeams` null APIs

## HasTeams

| Current                       | Target                                                   |
| ----------------------------- | -------------------------------------------------------- |
| `personalTeam(): ?Team`       | `TeamRepository::findPersonalTeam(UserId): Option`       |
| `teamRole(): ?TeamRole`       | `MembershipRepository::roleFor(UserId, TeamId): Option`  |
| `switchTeam(): bool`          | `SwitchTeam::handle(): Result<Unit, TeamSwitchRejected>` |
| `toUserTeams()` null sentinel | `flatMap` / `filter` with `Option`                       |

## EnsureTeamMembership

Replace inline `abort_if` chain with:

1. Build `ResolveCurrentTeamQuery` from route
2. Call `ResolveCurrentTeam::handle()` → `Result`
3. `match()` ok → continue; err → `abort(403)` or redirect

## Team Invitations

Replace `accepted_at === null` checks in domain paths with `InvitationState` enum or value object. Keep nullable columns in DB; map at infrastructure boundary.

## Livewire Team Pages

Convert `Auth::user()` without guards to authenticated user resolution via application layer. Render optional team state with `Option::match` in the component, not `??` in business logic.

## Filament

Filament is installed as a Delivery Tier adapter. Create resources under `App\Filament` and keep writes behind shared application use cases. Never backfill domain logic into Livewire pages and Filament resources in parallel without a shared use case.

## Verification After Each Slice

```bash
composer lint:check
composer analyse
php artisan test --filter=Team
```

Do not merge a slice that introduces new PHPStan errors or breaks existing feature tests.
