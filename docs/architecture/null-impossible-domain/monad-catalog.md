# Extended Monad Catalog

This catalog is a decision aid. It documents the extended functional-programming monads that commonly appear in cheatsheets, explains how they map to this Laravel app, and shows when each one should be considered.

It is not a planned build sequence. Production code currently uses the first-party `App\Monads` core: `Option`, `Result`, and `Unit`. Add another monad only when repeated application code shows that the new interface would keep related decisions in one place and reduce branching across callers.

## Status Key

| Status      | Meaning                                                                                                        |
| ----------- | -------------------------------------------------------------------------------------------------------------- |
| Implemented | First-party type under `App\Monads`. Use it now.                                                               |
| Framework   | Laravel or a project convention already gives enough structure. Do not wrap it unless call sites become noisy. |
| Candidate   | Worth implementing if a concrete use case repeats across the core.                                             |
| Reference   | Useful concept, but not a current fit for this app.                                                            |

## Current Production Core

| Monad               | Status                                  | Use When                                                    | Laravel Example                                                             |
| ------------------- | --------------------------------------- | ----------------------------------------------------------- | --------------------------------------------------------------------------- |
| `Option` / `Maybe`  | Implemented as `Option`, `Some`, `None` | A value may be absent and absence is expected.              | `TeamReader::findPersonalTeam()` returns `Option<Team>` instead of `?Team`. |
| `Result` / `Either` | Implemented as `Result`, `Ok`, `Err`    | A workflow may succeed or fail with a typed expected error. | `SwitchCurrentTeam::handle()` returns `Result<Unit, DomainError>`.          |
| `Unit`              | Implemented                             | A successful operation has no useful payload.               | Team switching succeeds with `Ok(Unit::instance())`.                        |

### Option

Use `Option` at infrastructure and delivery boundaries where framework APIs return `null`.

```php
return $team === null
    ? None::instance()
    : new Some($team);
```

Consider `Option` when the caller needs to decide what absence means. Do not use it for failures that need a message, status code, or audit trail; use `Result` instead.

### Result

Use `Result` for application workflows where expected failure is part of the business language.

```php
return $this->memberships
    ->findForUserAndTeam($command->userId, $command->teamId)
    ->match(
        some: fn (Membership $membership) => Ok::of(Unit::instance()),
        none: fn () => Err::of(new TeamAccessDenied($command->teamId, $command->userId)),
    );
```

Render `Err` only at delivery boundaries such as HTTP, Livewire, or Filament.

### Unit

Use `Unit` when success is meaningful but returning `true`, `null`, or the mutated model would blur the contract.

```php
return Ok::of(Unit::instance());
```

## Practical Catalog

| Monad                                           | Status                | Consider When                                                                                 | Avoid When                                                                               |
| ----------------------------------------------- | --------------------- | --------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------- |
| `Identity`                                      | Reference             | Teaching or proving an abstraction that works for every monad.                                | Application code would only wrap and unwrap the same value.                              |
| `Option` / `Maybe`                              | Implemented           | Expected absence should be explicit.                                                          | You need typed failure information.                                                      |
| `Result` / `Either`                             | Implemented           | Expected failure should be explicit and renderable.                                           | You need to accumulate multiple independent errors.                                      |
| `Unit`                                          | Implemented           | Success carries no payload.                                                                   | The success value has business meaning.                                                  |
| `Validation` / `Validated`                      | Candidate             | You must collect many validation failures instead of stopping at the first `Err`.             | A workflow should stop as soon as one step fails.                                        |
| `List`                                          | Framework             | You are transforming many values. Laravel `Collection` already provides the useful interface. | You only have one optional value; use `Option`.                                          |
| `NonEmptyList`                                  | Candidate             | At least one value is required by an invariant, such as at least one validation error.        | The collection may be empty and that is acceptable.                                      |
| `Reader`                                        | Framework / Candidate | Many pure functions need the same read-only context. Laravel DI often covers this already.    | The context is mutable, request-scoped framework state, or better passed as a command.   |
| `Writer`                                        | Candidate             | Pure logic should return a value plus accumulated domain events, audit entries, or warnings.  | Logging can stay at the boundary or event dispatch already models the output.            |
| `State`                                         | Candidate             | A pure workflow transforms state step by step and the final state matters.                    | Eloquent transactions or a use case already express the state transition clearly.        |
| `IO` / `Effect`                                 | Reference             | You need to describe side effects without running them.                                       | Laravel jobs, events, commands, and adapters already isolate side effects clearly.       |
| `Task` / `Future` / `Async`                     | Framework             | Deferred work is queued or broadcast. Laravel queues and events carry the operational model.  | The core can stay synchronous and return `Result`.                                       |
| `Resource` / `Managed`                          | Reference             | A workflow must acquire and release resources reliably.                                       | PHP `try/finally`, Laravel storage adapters, or transactions are enough at the boundary. |
| `Parser`                                        | Candidate             | You build a real parser for commands, imports, expressions, or a DSL.                         | Validation rules and form requests are sufficient.                                       |
| `Tree`                                          | Reference             | Recursive branching structures need `map`/`flatMap`.                                          | Eloquent adjacency-list packages already own the tree behavior.                          |
| `Probability` / `Distribution`                  | Reference             | You model weighted outcomes or stochastic computations.                                       | Product code needs deterministic domain decisions.                                       |
| `Store`                                         | Reference             | You model a focused value with a way to peek at neighboring states.                           | Ordinary read models and projections are clearer.                                        |
| `Continuation`                                  | Reference             | You need explicit control over the rest of a computation.                                     | It would hide ordinary PHP control flow.                                                 |
| `Codensity`                                     | Reference             | You need a performance wrapper around deeply nested binds.                                    | The app has no proven monadic performance issue.                                         |
| `Free`                                          | Reference             | You want to describe an interpreter-backed language of operations.                            | A use case or pipeline is simpler.                                                       |
| Monad transformers (`OptionT`, `ResultT`, etc.) | Reference             | You repeatedly stack monads in a language with strong higher-kinded type support.             | PHPStan generics and Laravel code would become harder to read than explicit adapters.    |

## Candidate Guidance

### Validation

`Result` short-circuits: the first `Err` stops the workflow. Validation sometimes needs the opposite behavior: collect every invalid field before rendering a response.

Consider a `Validation` monad if validation logic moves out of Laravel form requests and repeated code starts building arrays of errors by hand.

```php
$validation = ValidateTeamName::check($name)
    ->combine(ValidateInvitationEmail::check($email))
    ->map(fn (TeamName $name, EmailAddress $email) => new InviteMemberCommand($name, $email));
```

Until that repetition exists, return `Result<Command, ValidationErrors>` from validators and render the errors at the boundary.

### NonEmptyList

Use a `NonEmptyList` only when the type system should prove that at least one value exists. The most likely fit is validation errors:

```php
/** @var NonEmptyList<ValidationError> $errors */
return Err::of(new ValidationErrors($errors));
```

Do not replace ordinary `Collection` values just to get a functional name.

### Reader

`Reader<Environment, Value>` represents a computation that depends on read-only context. Laravel's container, config, and command objects usually make this unnecessary.

Consider it only for pure domain modules where passing the same context through every function becomes the main noise.

```php
$pricing = Reader::ask()
    ->map(fn (PricingContext $context) => $context->currency);
```

Prefer explicit command objects in application use cases.

### Writer

`Writer<Value, Log>` returns a value plus accumulated output. The app may need this if pure domain logic should collect domain events, warnings, or audit messages without dispatching them.

```php
$decision = TeamPolicyDecision::for($actor, $team)
    ->tell(new AccessChecked($actor->id, $team->id));
```

Prefer Laravel events when the event is already a side effect owned by the application boundary.

### State

`State<OldState, NewState>` is useful when a pure algorithm evolves state through many steps.

```php
$transition = InvitationWorkflow::accept()
    ->flatMap(fn () => InvitationWorkflow::recordAuditEntry());
```

For Eloquent writes, keep state changes in application use cases and database transactions.

### Parser

Parser combinators can help when the app accepts structured input more complex than a form: import formats, command languages, or rich search syntax.

```php
$parser = TeamImportParser::header()
    ->flatMap(fn (ImportHeader $header) => TeamImportParser::rows($header));
```

Do not introduce parser abstractions for ordinary request validation.

## Framework-Represented Monads

### List as Laravel Collection

Laravel `Collection` already provides the useful list-monad operations: `map`, `flatMap`, `filter`, `pipe`, and `pipeThrough`.

```php
return $teams
    ->filter(fn (Team $team) => $team->isVisibleTo($user))
    ->map(fn (Team $team) => UserTeam::fromTeam($team))
    ->values();
```

Use `Collection` for many values. Use `Option` for zero-or-one values.

### Task as Laravel Queue or Event

Queued jobs, events, broadcasts, and notifications already model deferred effects with operational concerns such as retries, backoff, serialization, and observability.

Keep the core synchronous:

```php
$result = $this->issueApiToken->handle($command);
```

Then dispatch work at the boundary after an `Ok`.

## Conversion Guidance

Conversions should express a change in meaning. Do not add conversion methods just to mirror a cheatsheet.

### Option to Result

Use this when absence becomes a typed business failure.

```php
$result = $teamOption->match(
    some: fn (Team $team) => Ok::of($team),
    none: fn () => Err::of(new TeamNotFound($teamId)),
);
```

Candidate helper:

```php
$result = $teamOption->toResult(fn () => new TeamNotFound($teamId));
```

Add `toResult()` only when this pattern appears often enough to make the interface smaller for callers.

### Result to Option

Use this when the error detail is intentionally irrelevant, such as a best-effort optional lookup in presentation code.

```php
$option = $result->match(
    ok: fn (Team $team) => new Some($team),
    err: fn () => None::instance(),
);
```

Candidate helper:

```php
$option = $result->toOption();
```

Do not discard errors in application workflows that must be rendered, logged, or audited.

### Result Error Mapping

Use this when infrastructure or validation errors need to become a domain/application error without changing the success value.

```php
$result = $result->match(
    ok: fn (TokenIssued $token) => Ok::of($token),
    err: fn (TokenProviderFailure $error) => Err::of(ApplicationError::from($error)),
);
```

Candidate helper:

```php
$result = $result->mapErr(fn (TokenProviderFailure $error) => ApplicationError::from($error));
```

### Validation to Result

Validation accumulates errors. `Result` renders a final success or failure.

```php
$result = $validation->match(
    valid: fn (InviteMemberCommand $command) => Ok::of($command),
    invalid: fn (NonEmptyList $errors) => Err::of(new ValidationErrors($errors)),
);
```

### Collection to Option

Use this when many values are reduced to the first matching value.

```php
$team = $teams->first(fn (Team $team) => $team->isPersonal());

return $team === null ? None::instance() : new Some($team);
```

Keep this conversion at the boundary where Laravel returns `null`.

## Adoption Rules

1. Start with `Option`, `Result`, `Unit`, and Laravel's existing abstractions.
2. Add a new monad only after at least two real call sites show repeated branching, tuple-passing, or hand-rolled accumulation.
3. Prefer an application-specific name when the domain concept is stronger than the functional name, such as `ValidationErrors` instead of a generic `NonEmptyList`.
4. Record significant additions in an ADR if the choice is hard to reverse, surprising, and the result of a real trade-off.
5. Keep `App\Monads` framework-free. Laravel, Eloquent, Filament, queues, and HTTP rendering stay outside the monad implementations.
