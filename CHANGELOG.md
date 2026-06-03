# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/woduda/civicrm-php/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/woduda/civicrm-php/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/woduda/civicrm-php/releases/tag/v0.1.0
