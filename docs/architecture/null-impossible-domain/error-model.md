# Error Model

## DomainError

Immutable, typed value objects describing business rule violations. Examples: `TeamAccessDenied`, `TeamSwitchRejected`, `InvitationExpired`.

Domain errors are **data**, not exceptions. They carry enough context for boundary renderers to produce user-facing messages without leaking infrastructure details.

```php
final readonly class TeamAccessDenied implements DomainError
{
    public function __construct(
        public TeamSlug $teamSlug,
        public UserId $userId,
    ) {}
}
```

## ApplicationError

Planned wrapper for domain errors or cross-cutting failures such as authorization and validation aggregation at the application layer. Until an `ApplicationError` type exists, application use cases return `Err` values carrying `DomainError` or a specific validation error object. Expected paths are still returned as `Err`, never thrown.

## Boundary Rendering

| Layer                | Renders to                                                     |
| -------------------- | -------------------------------------------------------------- |
| `App\Http`           | HTTP 403/404, redirects, session flash, Livewire validation    |
| `App\Filament`       | Filament notifications, form validation errors, denied actions |
| `App\Http\Responses` | Fortify redirect contracts                                     |

```php
$result->match(
    ok: fn (TeamContext $ctx) => $next($request),
    err: fn (DomainError $e) => match ($e) {
        TeamAccessDenied::class => abort(403),
        default => abort(403),
    },
);
```

## No try/catch Policy

**Allowed:** converting truly unexpected infrastructure failures at the outermost adapter (log + generic 500).

**Forbidden:** try/catch for expected business outcomes in domain, application, or Filament resource hooks.

Infrastructure may catch driver exceptions and map them to `Err(InfrastructureFailure)` once, at the repository boundary.

## Validation

Form requests and Filament forms collect raw input. Validators return `Result<Command, ValidationErrors>`. Invalid input never reaches use cases as untyped arrays.
