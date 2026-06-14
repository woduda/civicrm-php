# CLAUDE.md — civicrm-php

## Project

PSR-18 compatible client for CiviCRM REST APIv4. Framework-agnostic, immutable,
fully typed. Mirrors the ergonomics of modern API SDKs (Stripe-php style).

## Stack & standards

- PHP >= 8.3 (target 8.4, use 8.4 features where they improve clarity)
- PSR-4 autoloading: `Woduda\CiviCRM\` -> `src/`, `Woduda\CiviCRM\Tests\` -> `tests/`
- PER-CS 2.0 code style (Pint, `per` preset)
- PSR-7/17/18 for HTTP, PSR-3 for logging
- HTTP client discovery via php-http/discovery (NO hard concrete HTTP client dependency)
- Test-only HTTP stack: nyholm/psr7 (PSR-7/17) + php-http/mock-client (PSR-18) — no real client bundled
- Conventional Commits for all commits

## Non-negotiable code rules

- `declare(strict_types=1);` in EVERY php file
- All DTO/value objects: `final readonly class`
- No state mutation — builders return new instances (immutable with-er pattern)
- No `mixed` without an explicit, documented reason
- Full parameter and return types everywhere; no untyped properties
- No global/static mutable state
- Enums instead of string/int constants
- Named constructors over overloaded constructors
- Never throw \Exception/\RuntimeException directly — use the typed hierarchy in src/Exception
- No dependency on a concrete HTTP client in production code
- English identifiers, English PHPDoc, English commit messages

## Quality gates (MUST pass before every commit)

- `composer cs:check` -> Pint (PER-CS): 0 changes needed (`composer cs` auto-fixes)
- `composer phpstan` -> PHPStan level max + strict-rules, treatPhpDocTypesAsCertain false: 0 errors
- `composer rector` -> dry-run clean
- `composer test` -> all green, line coverage >= 90%
- `composer mutate` -> Pest native mutation testing, MSI >= 80% (covered MSI tracked separately)
  Combined: `composer qa` runs all of the above.

> Mutation testing uses Pest's native `--mutate` runner, NOT Infection. Infection
> 0.29 is incompatible with Pest 3's coverage report: the `P\` test-class prefix
> in the coverage XML is absent from the JUnit report, causing
> `TestFileNameNotFoundException`. Do not reintroduce Infection without verifying
> this is fixed upstream.

## Architecture

Two parallel entry-point layers exist in the codebase — the legacy one is kept for
backwards compatibility; all new entity APIs build on the new one.

**New layer (preferred for all new code):**

Entry point:
- `CiviCrmClient` (`src/CiviCrmClient.php`) — `final readonly`; `::create(Config)` factory auto-discovers HTTP client via `Transport::createDefault`; `entity(string): GenericApi` escape hatch for any entity; `raw(entity, action, params): array` for one-off requests; typed shortcuts for all implemented entities (see below)
- `TransportInterface` (`src/Contract/TransportInterface.php`) — `send(entity, action, params): ApiResponse`
- `Transport` (`src/Http/Transport.php`) — `final readonly` PSR-18 adapter; `createDefault(Config, ?RetryStrategy = null, ?LoggerInterface = null): self`. Wraps each `send()` in a retry loop (injectable `RetryStrategy`, default `NoRetry`), optional PSR-3 logging (debug per request, warning per retry, error on final failure), and an injectable sleeper (so tests never really sleep). Redacts the `values` payload (`[REDACTED]`) and never logs credentials. Catches PSR-18 `ClientExceptionInterface` and rethrows as `TransportException`.

Base classes:
- `AbstractEntityApi` (`src/Api/AbstractEntityApi.php`) — `abstract readonly`; 4 helpers: `executeGet(GetQuery): Result`, `executeAction(ActionRequest): Result`, `getFields(): array`, `getActions(): array`
- `AbstractContactSubEntityApi` (`src/Api/AbstractContactSubEntityApi.php`) — `abstract readonly extends AbstractEntityApi`; shared logic for contact sub-entities (Email, Phone, Address): `contactQuery(int): GetQuery`, `setPrimary(int)`, `remove(int)`, `updateById(int, array): FromArrayInterface`, `createRecord(array): FromArrayInterface`; requires `dtoClass(): class-string<FromArrayInterface>`
- `GenericApi` (`src/Api/GenericApi.php`) — `final readonly extends AbstractEntityApi`; public `get/create/update/save/delete/getFields/getActions`

Typed entity APIs (`final readonly extends AbstractEntityApi` unless noted):
- `ContactApi` — `get/getById/create/update/upsertByEmail/withTags/addToGroups/setCustomFields/updatePrimaryEmail/updatePrimaryPhone/updatePrimaryAddress/getFields/getActions`; constructor takes `(TransportInterface, CustomFieldResolver)`
- `ActivityApi` — `get/getById/create/forContact/getFields/getActions`
- `TagApi` — `ensureExists/tagContact/getFields/getActions`
- `GroupApi` — `ensureExists/addContact/removeContact/getFields/getActions`
- `EmailApi` (`extends AbstractContactSubEntityApi`) — `get/forContact/primary/setPrimary/add/remove/updateById/getFields/getActions`
- `PhoneApi` (`extends AbstractContactSubEntityApi`) — `get/forContact/primary/setPrimary/add/remove/updateById/getFields/getActions`
- `AddressApi` (`extends AbstractContactSubEntityApi`) — `get/forContact/primary/setPrimary/addFromData/updateFromData/remove/getFields/getActions`
- `RelationshipApi` — `get/create(contactIdA, contactIdB, type: string|int, startDate?, extra?)/terminate/forContact/ofType(typeName): GetQuery/getFields/getActions`
- `RelationshipTypeApi` — `get/byName/getFields/getActions`
- `NoteApi` — `get/addToContact/forContact/delete/getFields/getActions`
- `EventApi` — constructor takes `(TransportInterface, ClockInterface)`; `get/getById/findByTitle/upcoming/between/participantCount/isFull/getFields/getActions`
- `ParticipantApi` — `get/getById/register/forContact/forEvent/byStatus/markAttended/cancel/getFields/getActions`
- `ContributionApi` — constructor takes `(TransportInterface, FinancialTypeResolver)`; `get/getById/recordOneTime/create/forContact/totalsForContact/completedSince/markCompleted/refund/getFields/getActions`

Resolvers:
- `CustomFieldResolver` (`src/Api/CustomFieldResolver.php`) — `resolve(groupName, fieldName): string` returns the dotted APIv4 key (`"GroupName.field_name"`); fetches and caches field definitions per group
- `FinancialTypeResolver` (`src/Api/FinancialTypeResolver.php`) — `resolve(typeName): int` maps a human-readable financial type name to its integer ID; exposed via `CiviCrmClient::financialTypes()`
- `RelationshipTypeCache` (`src/Api/RelationshipTypeCache.php`) — in-memory cache used internally by `RelationshipTypeApi`

Results and hydration:
- `Result<T>` (`src/Result/Result.php`) — generic iterable result; `values: array<T>`, `count: int`, `first(): ?T`
- `TypedResult` (`src/Result/TypedResult.php`) — static utility; `hydrate(Result, class-string<T>): Result<T>` maps raw APIv4 rows through `T::fromArray()`
- `ApiResponse` (`src/Result/ApiResponse.php`) — raw transport response; `values: array<mixed>`, `count: int`

Contracts:
- `TransportInterface` (`src/Contract/TransportInterface.php`) — `send(entity, action, params): ApiResponse`
- `ClockInterface` (`src/Contract/ClockInterface.php`) — `now(): DateTimeImmutable`; implemented by `SystemClock` (`src/SystemClock.php`)
- `FromArrayInterface` (`src/Entity/FromArrayInterface.php`) — `fromArray(array): static`; implemented by all entity DTOs

Resilience — retry (`src/Retry/`):
- `RetryStrategy` (`src/Retry/RetryStrategy.php`) — `shouldRetry(int $attempt, \Throwable): bool`, `delayMs(int $attempt, ?\Throwable = null): int` (the `?\Throwable` lets a strategy honor `Retry-After`)
- `NoRetry` (`final readonly`) — default; zero retries (preserves single-attempt behavior)
- `ExponentialBackoff` (`final readonly`) — `(maxAttempts=3, baseDelayMs=200, multiplier=2.0, maxDelayMs=5000, jitter=true)`; full jitter, `maxDelayMs` cap. Retries ONLY transient failures: `TransportException`, `RateLimitException` (honors `Retry-After`, capped at `maxDelayMs`), and `ApiErrorException` with a 5xx `httpStatus`. NEVER retries `ValidationException` / `AuthenticationException`.

Exceptions (`src/Exception/`):
- `CivicrmException` — marker interface implemented by every library exception (catch-all)
- `ApiErrorException` — base for HTTP 4xx/5xx error responses; `final readonly ?int $httpStatus`; `fromResponse(ResponseInterface): self` routes 429 -> `RateLimitException` (parses `Retry-After`), 401/403 -> `AuthenticationException`, else -> `ApiErrorException`. NOTE: non-`final` (it is a base).
- `RateLimitException extends ApiErrorException` (`final`) — adds `?int $retryAfterSeconds`
- `AuthenticationException extends ApiErrorException` (`final`)
- `TransportException` (`final`) — wraps PSR-18 `ClientExceptionInterface` network errors; `fromThrowable(\Throwable): self`
- `ValidationException` (`final`, extends `InvalidArgumentException`) — invalid builder input; outside the `ApiErrorException` tree, never retried
- `ApiException` (`src/Exception/ApiException.php`) — DEPRECATED `class_alias` of `ApiErrorException` (same class; keeps `catch`/`instanceof` working), to be removed in 1.0. PHPStan resolves it via a `bootstrapFiles` entry in `phpstan.neon`; do NOT reference `ApiException` in new code.

Entity DTOs (all `final readonly` implementing `FromArrayInterface`):
- `Contact`, `Activity`, `Tag`, `Group`, `Email`, `Phone`, `Address`, `AddressData`, `Note`
- `Relationship`, `RelationshipType`
- `Event`, `Participant`
- `Contribution`, `ContributionTotals`
- Enums: `ContributionStatus`, `ParticipantStatus`

**Legacy layer (do not extend):**
- `Client` (`src/Client.php`) — PSR-18 HTTP transport; `sendRequest(uri, params): ApiResponse`
- `EntitiesApi` (`src/Api/EntitiesApi.php`) — abstract base for old typed subclasses (`ContactsApi`, `ActivitiesApi`, `AddressesApi`, `EmailsApi`, `EventsApi`, `ParticipantsApi`, `PhonesApi`, `ContributionsApi`)

**Coding notes for the new layer:**
- `abstract readonly class` requires the child to also be `readonly` (PHP 8.2+ enforced)
- When overriding a `protected` method as `public` in a child class, add `#[\Override]` — Rector enforces this
- `resolveWhere(GetQuery|array): array` pattern for where-coercion: use `is_array($params['where'] ?? null)` to safely extract the where key from `GetQuery::toParams()` (PHPStan sees array access as `mixed`; `is_array` narrows it)
- Action name convention in transport calls: all lowercase (`getfields`, `getactions`, `get`, `create`, etc.)
- New entity constructors that need a resolver (e.g. `CustomFieldResolver`, `FinancialTypeResolver`) receive it as a second parameter; `CiviCrmClient` wires them up

## Testing

- Pest 3
- Unit tests: NO network. Two test-client helpers live in `tests/Pest.php`:
  - `civicrmClient(): [Client, MockClient]` — for HTTP-level tests (old layer)
  - `civicrmNewClient(): [CiviCrmClient, SpyTransport]` — for transport-level tests (new layer)
- `SpyTransport` (defined in `tests/Pest.php`) — in-memory spy implementing `TransportInterface`; `queue(ApiResponse)` to preset a response; `$spy->calls` to assert dispatched entity/action/params
- `SpyLogger` (in `tests/Pest.php`) — in-memory PSR-3 logger (`extends AbstractLogger`); `records`, `recordsAt(level)`, `dump()` for asserting logged content (and that secrets never leak)
- `SpySleeper` (in `tests/Pest.php`) — records requested sleep ms without sleeping; pass to `Transport` as `$spy(...)` and assert `$spy->calls`
- Transport retry tests use `civicrmClient()` + `MockClient`: `addResponse()` queues responses FIFO (e.g. two 503s then 200), `addException()` queues a thrown PSR-18 error first (use `Http\Client\Exception\TransferException` so the mock accepts it)
- Fixtures: real CiviCRM APIv4 JSON responses in tests/Fixtures/\*.json
- Every public method has tests, including error paths (4xx, 5xx, malformed JSON)
- Use datasets for operator/edge-case matrices

## Documentation

- PHPDoc on every public method, including @throws for each thrown exception type
- README with a runnable example per API class
- docs/ with one guide per topic (quickstart, querying, entities, webhooks, errors)
- Keep CHANGELOG.md (Keep a Changelog format)

## CiviCRM APIv4 REST facts (authoritative)

- Endpoint: POST {base}/civicrm/ajax/api4/{Entity}/{Action}
- Required header: X-Requested-With: XMLHttpRequest
- Auth (authx extension): Authorization: Bearer {token} (preferred)
  Fallback: api_key + key (site key) as params
- Request body: form-encoded field `params` containing a JSON object
- params keys: select, where, orderBy, limit, offset, values, chain, groupBy, having, join
- where clause shape: [["field", "OPERATOR", value], ...]
- Success response: {"values": [...], "count": N}
- Error: non-2xx + {"error_code": ..., "error_message": ...}

## What this library is NOT

- Not a Laravel package (the Laravel adapter lives in a separate repo civicrm-php-laravel)
- Not an ORM. It is a typed, ergonomic transport over APIv4.
