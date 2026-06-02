# ADR-0001: Adopt Null Impossible Domain Architecture

## Status

Accepted

## Context

The application uses Laravel with nullable Eloquent patterns, optional chaining, and HTTP aborts for expected access failures. We need stricter type safety and explicit modeling of absence and failure for agent-navigable code and 100% quality gates.

## Decision

Adopt a layered Null Impossible Domain architecture in `App\` with custom monads, domain/application/infrastructure separation, and delivery adapters for HTTP, Livewire, and Filament.

## Alternatives Considered

- **Laravel-default nullable Eloquent** — familiar but keeps invalid states representable
- **Exception-first service layer** — hides control flow; expensive for expected failures
- **Third-party functional library** — extra dependency; less control over Laravel integration
- **Custom monads (chosen)** — framework-free core, explicit boundaries, enforceable by static analysis

## Consequences

- Positive: Clear boundaries, testable use cases, enforceable architecture rules
- Negative: Migration cost for existing `HasTeams` and middleware patterns
- Negative: Steeper onboarding for contributors used to nullable Laravel style
