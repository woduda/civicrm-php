# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Typed entity DTOs: `Email`, `Phone`, `Address`, and input DTO `AddressData` under
  `src/Entity/`.
- Typed sub-entity APIs: `EmailApi`, `PhoneApi`, `AddressApi` with `forContact()`,
  `primary()`, `setPrimary()`, `add()`, and `remove()`.
- `ContactApi` shortcuts: `updatePrimaryEmail()`, `updatePrimaryPhone()`,
  `updatePrimaryAddress()`.
- `CiviCrmClient::emails()`, `phones()`, `addresses()` entry points.

- `FromArrayInterface` and typed entity DTOs under `src/Entity/`:
  `Contact`, `Activity`, `Tag`, `Group` — each with `fromArray()`, `toArray()`, and
  `rawData` preserving the full APIv4 row.
- `Result` (`src/Result/Result.php`) — iterable, countable collection wrapping APIv4
  `values` and server-reported `count`; supports `first()`, `isEmpty()`, `map()`,
  `filter()`, and PHPStan `@template` generics.
- `TypedResult::hydrate()` — hydrates raw `Result` rows into typed entity DTOs.

### Changed

- **Breaking:** `ContactApi::get()`, `create()`, `update()`, and `upsertByEmail()` now
  return `Result<Contact>` instead of `array`.
- **Breaking:** `ContactApi::getById()` now returns `?Contact` instead of `?array`.
- **Breaking:** `ActivityApi::get()`, `create()`, and `logForContact()` now return
  `Result<Activity>` instead of `array`.
- Migration: replace `$rows[0]['id']` with `$result->first()?->id`; iterate typed
  objects via `foreach ($result as $contact)`.
- `GenericApi` CRUD methods still return `array` (unchanged public API).

## [0.3.0] - 2026-06-03

### Added

- `ContactApi` (`src/Api/ContactApi.php`) — typed Contact API with `get(GetQuery)`,
  `getById(int): ?array`, `create(array)`, `update(int, array)`, `upsertByEmail(string, array)`,
  `withTags(int, list<string>)`, `addToGroups(int, list<string>)`,
  `setCustomFields(int, string, array)`, `getFields()`, `getActions()`.
  `upsertByEmail` is implemented as two sequential requests (Contact.get + conditional
  create/update); it is not atomic and documents this as a known limitation.
  `withTags` and `addToGroups` create missing tags/groups on the fly and write
  memberships via idempotent `EntityTag.save` / `GroupContact.save` with `match`.
- `ActivityApi` (`src/Api/ActivityApi.php`) — `create(array)`, `get(GetQuery)`,
  `logForContact(int, string, array $extra)` (defaults `status_id.name = 'Completed'`;
  `$extra` overrides any default), `forContact(int): GetQuery` (pre-filtered query),
  `getFields()`, `getActions()`.
- `TagApi` (`src/Api/TagApi.php`) — `ensureExists(string): int` (get-or-create, returns
  ID), `tagContact(int, string)` (idempotent via `EntityTag.save` with match),
  `getFields()`, `getActions()`.
- `GroupApi` (`src/Api/GroupApi.php`) — `ensureExists(string): int`, `addContact(int, int)`
  (idempotent, `status = 'Added'`), `removeContact(int, int)` (updates status to
  `'Removed'` to preserve audit trail), `getFields()`, `getActions()`.
- `CustomFieldResolver` (`src/Api/CustomFieldResolver.php`) — validates that a
  `"GroupName.field_name"` combination exists in CiviCRM via `CustomField.get`, caches
  results per instance, and throws `ValidationException::unknownCustomField()` when the
  field is absent.
- `ValidationException::unknownCustomField(string, string)` named constructor.
- `CiviCrmClient::contacts()`, `activities()`, `tags()`, `groups()` now return the typed
  API classes above instead of `GenericApi`; removed the PR#4 TODO comments.
- `SpyTransport` in `tests/Pest.php` upgraded from a single-slot response holder to a
  FIFO queue — `queue()` can now be called multiple times to preload per-call responses.
- Six JSON fixture files under `tests/Fixtures/` documenting real CiviCRM 5.x response
  shapes for contacts, tags, groups, and custom fields.

## [0.2.0] - 2026-06-03

### Added

- `CiviCrmClient` — new primary entry point with a `::create(Config)` factory that
  auto-discovers the PSR-18 client. Provides typed entity shortcuts (`contacts()`,
  `activities()`, `tags()`, `groups()`), a generic `entity(string)` accessor, and a
  `raw(entity, action, params)` escape hatch for actions not covered by typed methods.
- `TransportInterface` (`src/Contract/`) — single-method interface
  `send(entity, action, params): ApiResponse`, decoupling entity API classes from
  the HTTP layer and making unit tests transport-only (no mock HTTP needed).
- `Transport` (`src/Http/`) — `final readonly` PSR-18 adapter implementing
  `TransportInterface`; wraps the existing `Client`.
- `AbstractEntityApi` (`src/Api/`) — `abstract readonly` base class for typed entity
  APIs; accepts `TransportInterface` + entity name via constructor; protected helpers:
  `executeGet(GetQuery)`, `executeAction(ActionRequest)`, `getFields()`, `getActions()`.
- `GenericApi` (`src/Api/`) — `final readonly` generic entity API. Public CRUD methods
  accept domain objects directly — no `.toParams()` glue required — and return the
  values array: `get(GetQuery)`, `create(array)`, `update(array, GetQuery|array)`,
  `save(list)`, `delete(GetQuery|array)`, `getFields()`, `getActions()`.
- `SpyTransport` test double and `civicrmNewClient()` factory in `tests/Pest.php` for
  unit-testing entity API classes without any HTTP stack.

## [0.1.0] - 2026-06-03

### Added

- Immutable query layer under `src/Query/`: `GetQuery` and `ActionRequest`
  with-er builders, `Operator`/`Conjunction` enums and a `ChainBuilder` for
  APIv4 chaining, plus a `CivicrmException` interface and `ValidationException`.
- `Client` and `Config` — PSR-18 HTTP transport and configuration value object;
  entity accessors for Contact, Activity, Address, Email, Phone, Event, Participant
  and Contribution via the `EntitiesApi` hierarchy.
- `ApiResponse` — immutable response value object with `values`, `count`, `version`.
- `ApiException` — typed exception for HTTP 4xx/5xx responses.
- Project foundation and quality tooling: PHPStan (level max + strict rules),
  Pint (PER-CS), Rector (up to PHP 8.4), Pest test suite with code coverage and
  native mutation testing.
- Continuous integration workflow running the full quality suite on PHP 8.3 and 8.4.
- `CONTRIBUTING.md` and this changelog.

[Unreleased]: https://github.com/woduda/civicrm-php/compare/v0.3.0...HEAD
[0.3.0]: https://github.com/woduda/civicrm-php/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/woduda/civicrm-php/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/woduda/civicrm-php/releases/tag/v0.1.0
