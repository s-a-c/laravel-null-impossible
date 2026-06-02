# Filament In A Null Impossible Domain

## Position In The Architecture

Filament is a **delivery adapter**. Panels, resources, pages, forms, tables, actions, widgets, and relation managers live at the same boundary level as HTTP and Livewire code in `App\Http`.

Filament is server-driven UI (Livewire + Alpine.js + Tailwind). It integrates with Laravel model policies for standard CRUD, but custom actions require explicit authorization and must delegate business decisions to `App\Application` use cases.

## Allowed Dependencies

Filament classes may depend on:

- Filament, Livewire, Laravel authorization
- Eloquent model identifiers and presentation queries
- DTOs intended for UI projection
- `App\Application` use cases and commands
- `App\Http` boundary render helpers (notifications, validation mapping)

## Forbidden Dependencies

Filament classes must not:

- Contain domain decisions or invariant checks
- Use direct nullable domain branching (`?->` on domain concepts)
- Use try/catch for expected use-case failures
- Perform raw `DB` writes or `Model::create()` for domain mutations
- Import low-level infrastructure repositories directly (go through application layer)

## Resource Operations

Create, update, delete, restore, force-delete, attach, detach, and custom actions **call application use cases**. The resource converts `Result` into Filament notifications, validation errors, redirects, or denied actions.

## Forms And Tables

- **Forms** collect input and map to command objects (e.g. `CreateTeamCommand`)
- **Tables** display projections (e.g. `UserTeam` readonly DTOs)
- Neither forms nor tables decide whether a domain operation is allowed

## Authorization

- Use Laravel model policies for standard resource access (`viewAny`, `create`, `update`, `delete`)
- Use `->authorize('update')` on custom actions
- Explicitly authorize custom pages, relation managers, widgets, and bulk actions

## Eloquent Boundary

Filament may reference Eloquent models for resource identity and table queries. Nullable lookups (`first()`, route binding) are converted to `Option` or `Result` in the resource hook **before** calling application code.

## Examples

### Create action

```php
protected function handleRecordCreation(array $data): Model
{
    $result = $this->createTeam->handle(new CreateTeamCommand(
        userId: UserId::fromAuth(),
        name: TeamName::fromString($data['name']),
    ));

    return $result->match(
        ok: fn (Team $team) => $team,
        err: fn (DomainError $e) => $this->failFromDomainError($e),
    );
}
```

### Custom member removal action

```php
Action::make('removeMember')
    ->authorize('update')
    ->action(function (User $member) {
        $result = $this->removeMember->handle(new RemoveMemberCommand(
            teamId: TeamId::fromModel($this->record),
            userId: UserId::fromModel($member),
            actorId: UserId::fromAuth(),
        ));

        $result->match(
            ok: fn () => Notification::make()->success()->title('Member removed')->send(),
            err: fn (DomainError $e) => $this->notifyError($e),
        );
    });
```

### Relation manager

Relation managers follow the same pattern: table for display, actions call use cases, `match()` on `Result` for feedback.

## Anti-Pattern Checklist

- [ ] No domain rules in `mutateFormDataBeforeCreate`
- [ ] No try/catch wrapping expected use-case `Err` paths
- [ ] No `?->` when calling application services
- [ ] No direct `Team::create()` from resource hooks
- [ ] No nullable return types on custom Filament helper methods that represent domain concepts

## Pest Architecture Expectations

`App\Filament` may depend on:

- `App\Application`
- `App\Http` rendering helpers
- Filament, Livewire
- Eloquent presentation models

`App\Filament` must not depend on:

- `App\Infrastructure` repositories directly
- `App\Domain` persistence internals

Enforced in `tests/Architecture/NullImpossibleArchitectureTest.php`.

## Installed Filament Stack

The starter kit ships a **minimal** Filament delivery adapter: `filament/filament` with one admin panel and a Team resource example. Authorization uses Laravel model policies, not a bundled permission plugin stack.

Reference scaffold:

- Admin panel provider: `App\Providers\Filament\AdminPanelProvider`
- Team resource: `App\Filament\Admin\Resources\Teams\TeamResource`

Add Filament plugins only when a generated app needs them; keep business rules in `App\Application`.
