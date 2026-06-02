# ADR-0006: Treat Filament as Delivery Adapter

## Status

Accepted

## Context

Filament resources tempt inline Eloquent mutations and domain logic in form/table hooks. This violates null impossible boundaries.

## Decision

Place Filament code in `App\Filament`. Resources, actions, and relation managers delegate to `App\Application` use cases and render `Result` at the Filament boundary. Policies handle standard authorization; custom actions use explicit `authorize()`.

## Alternatives Considered

- **Filament as application layer** — blurs UI and use cases
- **Direct Eloquent in resources** — fast to write; untestable domain logic

## Consequences

- Positive: Filament additions follow same rules as Livewire/HTTP
- Negative: More boilerplate than default Filament tutorials
- Negative: Filament not yet installed; guidance applies when added
