# CLAUDE.md — civicrm-php

## Project

PSR-18 compatible client for CiviCRM REST APIv4. Framework-agnostic, immutable,
fully typed. Mirrors the ergonomics of modern API SDKs (Stripe-php style).

## Stack & standards

- PHP >= 8.3 (target 8.4, use 8.4 features where they improve clarity)
- PSR-4 autoloading, PER-CS 2.0 code style
- PSR-7/17/18 for HTTP, PSR-3 for logging
- HTTP client discovery via php-http/discovery (NO hard Guzzle dependency)
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

- `composer phpstan` -> PHPStan level max + strict-rules: 0 errors
- `composer cs` -> Pint (PER-CS): 0 changes needed
- `composer test` -> all green, line coverage >= 90%
- `composer infection`-> MSI >= 80%
- `composer rector` -> dry-run clean
  Combined: `composer qa` runs all of the above.

## Testing

- Pest 3
- Unit tests: NO network. Inject a mock Psr\Http\Client\ClientInterface.
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
