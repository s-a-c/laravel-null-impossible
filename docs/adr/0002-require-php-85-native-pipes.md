# ADR-0002: Require PHP 8.5 and Native Pipe Operator

## Status

Accepted

## Context

Fluid composition is a core architectural preference. PHP 8.5 adds the native `|>` pipe operator for left-to-right callable chaining.

## Decision

Raise minimum PHP to `^8.5`. Allow native `|>` in domain and application code for pure single-argument transforms. Use Laravel Pipeline for DI-backed multi-stage workflows.

## Alternatives Considered

- **Stay on PHP 8.3** — broader hosting compatibility; no native `|>`
- **PHP 8.5 with Laravel pipes only** — avoids new syntax but rejects user preference for native pipes

## Consequences

- Positive: Readable pure transform chains
- Negative: CI and local dev must run PHP 8.5+
- Negative: Cannot deploy to PHP 8.3/8.4 hosts
