# Glossary

## Null Impossible

A design rule: expected absence and expected failure are never represented with nullable types, sentinel values, or exception-driven control flow in domain and application code.

## Monad

A small wrapper around a value or workflow state that lets application code compose expected absence, success, or failure without nullable returns, sentinel values, or exception-driven branching.

## Option

A value that is either present (`Some`) or intentionally absent (`None`). Replaces `?T` when absence is an expected outcome.

## Result

A value that is either success (`Ok`) or failure (`Err`). Replaces `false`, thrown exceptions, and `abort()` for expected business outcomes.

## Unit

A value used when an operation succeeds but has no meaningful success payload.

## DomainError

A typed, immutable description of a business rule violation. Domain errors are data, not exceptions.

## Boundary Adapter

A layer that translates between framework APIs (HTTP, Livewire, Filament, Eloquent) and the null-impossible core. Nullable framework values are converted to `Option` or `Result` at this boundary.

## Team

An organization unit that groups users and owns shared resources.

## Membership

The relationship between a user and a team, including the member's role.

## Current Team

The team a user is actively working within for scoped routes and UI context.

## Personal Team

The default team created for a user at registration, marked as personal and non-deletable.

## Delivery Tier

Framework-facing adapters (Filament panels/resources, HTTP rendering glue) that integrate the system but do not carry core business rules.

## Core Tier

Null-impossible domain and application code (`App\Monads`, `App\Domain`, `App\Application`) that must satisfy strict 100% type/line/mutation gates.

## Starter Kit

The published Composer project `s-a-c/laravel-null-impossible` used as a template when creating new Laravel applications.

## Generated App

A new Laravel application produced from the starter kit via `laravel new` or `composer create-project`. It inherits the architecture and examples but is owned by the developer who created it.

## API Token

A credential issued for API access. Token issuance is an application workflow that returns `Result`, while storage and hashing remain framework concerns.

## Channel Authorization

The decision that a user may subscribe to a broadcast channel. Route callbacks delegate the decision to application code and render the resulting `Result` at the boundary.

## Panel

A Filament admin surface. Panels are delivery-tier interfaces that present and trigger workflows but do not own business decisions.
