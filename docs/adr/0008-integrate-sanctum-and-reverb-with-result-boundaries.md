# ADR 0008: Integrate Sanctum And Reverb With Result Boundaries

## Status

Accepted

## Context

The platform needs API tokens and realtime broadcasting while preserving null-impossible rules in domain and application code.

## Decision

- Install and configure `laravel/sanctum` and `laravel/reverb`.
- Keep framework auth/broadcasting concerns at the delivery boundary.
- Introduce application use cases that return `Result`:
    - `IssueApiToken`
    - `AuthorizeUserChannel`
- Render `Result` values in HTTP/broadcast adapters (`routes/api.php`, `routes/channels.php`) instead of throwing expected exceptions.

## Consequences

- Token issuance and channel authorization are explicit success/error flows.
- Domain failures are modeled as typed `DomainError` values (`TokenIssueDenied`, `ChannelAccessDenied`).
- Framework adapters stay thin and deterministic.
