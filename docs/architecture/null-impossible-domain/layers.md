# Layers

## App\Monads

Framework-free monadic types: `Option`, `Some`, `None`, `Result`, `Ok`, `Err`, `Unit`.

**May depend on:** PHP standard library only.

**Must not depend on:** Laravel, Eloquent, Livewire, Filament, HTTP.

## App\Domain

Pure business language: value objects, enums, domain errors, invariants.

**May depend on:** `App\Monads`, `App\Domain`.

**Must not depend on:** Eloquent, facades, HTTP, Livewire, Filament, database, `abort`, try/catch for control flow.

## App\Application

Use cases and pipelines. Accepts command objects, returns `Result<Success, DomainError>`.

**May depend on:** `App\Monads`, `App\Domain`, application contracts, infrastructure interfaces.

**Must not depend on:** Filament, Livewire views, HTTP request/response types (except dedicated boundary renderers in `App\Http`).

## App\Infrastructure

Persistence and framework adapters. Converts nullable Eloquent/framework APIs into `Option` or `Result` at the edge.

**May depend on:** `App\Monads`, `App\Domain`, `App\Application` contracts, Eloquent, Laravel.

**Must not contain:** UI rendering, Filament resource logic.

## App\Http

HTTP middleware, Fortify responses, Livewire pages, boundary renderers.

**May depend on:** `App\Application`, `App\Monads`, Laravel HTTP/Livewire.

**May:** call `abort`, redirect, flash, render validation errors — only here and in `App\Filament`.

## App\Filament

Filament panels, resources, pages, forms, tables, actions, widgets, relation managers.

**May depend on:** `App\Application`, `App\Monads`, Filament, Livewire, policies, Eloquent for presentation queries.

**Must not:** embed domain rules, direct `DB` writes for domain mutations, or try/catch expected use-case failures.

## App\Models (transitional)

Existing Eloquent models remain infrastructure-facing. New domain logic lives in `App\Domain`; models are mapped at the infrastructure boundary until fully migrated.

## Dependency Matrix

| From \ To | Monads | Domain | Application | Infrastructure | Http | Filament | Models |
|-----------|--------|--------|-------------|----------------|------|----------|--------|
| Monads | — | | | | | | |
| Domain | yes | yes | | | | | |
| Application | yes | yes | yes | via interfaces | | | |
| Infrastructure | yes | yes | contracts | yes | | | yes |
| Http | yes | | yes | | yes | | yes |
| Filament | yes | | yes | | helpers | yes | yes |
