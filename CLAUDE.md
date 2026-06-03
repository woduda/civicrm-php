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
- `CiviCrmClient` (`src/CiviCrmClient.php`) — `final readonly` entry point; `::create(Config)` factory uses `Transport::createDefault`; `entity(string): GenericApi` for arbitrary entities; typed shortcuts (`contacts()`, `activities()`, `tags()`, `groups()`) are placeholders (TODO PR#4) that currently return `GenericApi`
- `TransportInterface` (`src/Contract/TransportInterface.php`) — `send(entity, action, params): ApiResponse`
- `Transport` (`src/Http/Transport.php`) — `final readonly` PSR-18 adapter wrapping `Client`; `createDefault(Config): self`
- `AbstractEntityApi` (`src/Api/AbstractEntityApi.php`) — `abstract readonly`; 4 protected helpers: `executeGet(GetQuery)`, `executeAction(ActionRequest)`, `getFields()`, `getActions()`; return `array` for now (TODO PR#5: return `Result`)
- `GenericApi` (`src/Api/GenericApi.php`) — `final readonly extends AbstractEntityApi`; public `get/create/update/save/delete/getFields/getActions`

**Legacy layer (do not extend):**
- `Client` (`src/Client.php`) — PSR-18 HTTP transport; `sendRequest(uri, params): ApiResponse`
- `EntitiesApi` (`src/Api/EntitiesApi.php`) — abstract base for old typed subclasses (ContactsApi, ActivitiesApi, etc.)

**Coding notes for the new layer:**
- `abstract readonly class` requires the child to also be `readonly` (PHP 8.2+ enforced)
- When overriding a `protected` method as `public` in a child class, add `#[\Override]` — Rector enforces this
- `resolveWhere(GetQuery|array): array` pattern for where-coercion: use `is_array($params['where'] ?? null)` to safely extract the where key from `GetQuery::toParams()` (PHPStan sees array access as `mixed`; `is_array` narrows it)
- Action name convention in transport calls: all lowercase (`getfields`, `getactions`, `get`, `create`, etc.)

## Testing

- Pest 3
- Unit tests: NO network. Two test-client helpers live in `tests/Pest.php`:
  - `civicrmClient(): [Client, MockClient]` — for HTTP-level tests (old layer)
  - `civicrmNewClient(): [CiviCrmClient, SpyTransport]` — for transport-level tests (new layer)
- `SpyTransport` (defined in `tests/Pest.php`) — in-memory spy implementing `TransportInterface`; `queue(ApiResponse)` to preset a response; `$spy->calls` to assert dispatched entity/action/params
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
