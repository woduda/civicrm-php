# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Configurable retry layer under `src/Retry/`:
  - `RetryStrategy` contract — `shouldRetry(int $attempt, Throwable $e): bool` and
    `delayMs(int $attempt, ?Throwable $e = null): int`.
  - `ExponentialBackoff` (`final readonly`) — exponential back-off with optional full
    jitter (`maxAttempts`, `baseDelayMs`, `multiplier`, `maxDelayMs`, `jitter`). Retries
    only transient failures: `TransportException`, `RateLimitException` (honoring
    `Retry-After`, capped at `maxDelayMs`), and `ApiException` with a 5xx `httpStatus`.
    Never retries `ValidationException` or `AuthenticationException`.
  - `NoRetry` (`final readonly`) — default strategy; zero retries, preserving the prior
    single-attempt behavior.
- `Transport` now accepts an optional `RetryStrategy` (defaults to `NoRetry`), an optional
  PSR-3 `LoggerInterface`, and an injectable sleeper. It logs a redacted `debug` entry per
  request, a `warning` per retry, and an `error` on final failure. The `values` payload is
  masked (`[REDACTED]`) and credentials (which live in request headers) never reach the log.
  `Transport::createDefault()` gains optional `?RetryStrategy` and `?LoggerInterface`
  parameters.

### Changed

- Enriched the exception hierarchy so retry decisions can be made:
  - `ApiException` now carries a nullable `httpStatus`; `ApiException::fromResponse()`
    captures the HTTP status and routes 429 → `RateLimitException` (parsing `Retry-After`),
    401/403 → `AuthenticationException`, everything else → `ApiException`.
  - Added `RateLimitException` (with `retryAfterSeconds`), `AuthenticationException`, and
    `TransportException` (wrapping PSR-18 `ClientExceptionInterface` network errors).

## [0.7.1] - 2026-06-09

### Changed

- Updated `CLAUDE.md` architecture section to reflect current codebase: removed
  stale TODO placeholders, documented all 13 typed entity APIs, resolvers
  (`CustomFieldResolver`, `FinancialTypeResolver`, `RelationshipTypeCache`),
  `AbstractContactSubEntityApi`, `TypedResult`, contracts, and entity DTOs.
- Updated `README.md`: added Email, Phone, Address, Relationship, and Contribution
  API sections to the table of contents; added `contributions()` and
  `financialTypes()` to the typed-entity-shortcuts table; fixed CRUD section
  (`update/save/delete` examples now use `entity()` / `GenericApi`, not
  `contacts()`); corrected Responses section to show `Result<T>` for typed APIs
  and `array<mixed>` for `GenericApi` / `raw()`.

## [0.7.0] - 2026-06-08

### Added

- `ClockInterface` (`src/Contract/ClockInterface.php`) — single-method contract
  (`now(): DateTimeImmutable`) for injectable wall-clock time. `SystemClock`
  (`src/SystemClock.php`) is the default production implementation.
- `ParticipantStatus` backed string enum for the CiviCRM `participant_status` option
  group (Registered, Attended, No-show, Cancelled, Pending from pay later, On waitlist,
  Awaiting approval, Rejected, Expired). Provides `fromId(int)` for default CiviCRM
  integer ID mapping and three classification methods:
  - `isPositive()` — Registered, Attended (counted against `max_participants`).
  - `isPending()` — Pending from pay later, On waitlist, Awaiting approval.
  - `isNegative()` — No-show, Cancelled, Rejected, Expired.
- Typed entity DTO `Event` under `src/Entity/` with `id`, `title`, `summary`,
  `description`, `startDate` / `endDate` (`DateTimeImmutable`), `eventTypeId`,
  `isActive`, `isPublic`, `maxParticipants`, and `defaultRoleId`.
- Typed entity DTO `Participant` under `src/Entity/` with `id`, `contactId`,
  `eventId`, `status` (`ParticipantStatus`), `roleId`, `registerDate`
  (`?DateTimeImmutable`), and `source`. Status is hydrated from `status_id:name`
  (string) or `status_id` (int) with a fallback to `Registered`.
- Typed `EventApi`: `get(GetQuery)`, `getById(int)`, `findByTitle(string)`,
  `upcoming(limit)` (filters `start_date > now` with injected clock for deterministic
  tests), `between(from, to)`, `participantCount(eventId, ?status)` (single
  `Participant.get` with `select=['row_count']`), `isFull(eventId)` (compares
  positive-class participant count against `max_participants`; false when no cap).
- Typed `ParticipantApi`: `get(GetQuery)`, `register(contactId, eventId, status,
  roleId, source, customFields)`, `markAttended(participantId)`, `cancel(participantId,
  ?reason)` (optionally creates a Follow Up activity with the reason),
  `checkIn(participantId, ?at)` (alias of markAttended; optionally creates a Check-in
  activity with the check-in timestamp), `forEvent(eventId, ?status)`,
  `forContact(contactId)` (ordered by `register_date DESC`), `countByStatus(eventId)`
  (single grouped transport call returning `array<string,int>` of status name → count).
- `CiviCrmClient::events()` and `CiviCrmClient::participants()` entry points.

## [0.6.0] - 2026-06-08

### Added

- Typed entity DTO `Contribution` under `src/Entity/`, representing a CiviCRM `Contribution`
  record with `id`, `contactId`, `totalAmount`, `currency`, `receiveDate` (`DateTimeImmutable`),
  `status` (`ContributionStatus` enum), `financialTypeId`, `source`, `invoiceNumber`, `trxnId`,
  `paymentInstrumentId`, and `campaignId`. Hydrates status from `contribution_status_id:name`
  (string) or `contribution_status_id` (int) with a fallback to `Pending`.
- `ContributionStatus` backed string enum for the CiviCRM `contribution_status` option group
  (Completed, Pending, Cancelled, Failed, InProgress, Overdue, Refunded, PartiallyPaid,
  ChargebackReceived). Provides `fromId(int)` for default CiviCRM integer ID mapping.
- `ContributionTotals` readonly DTO for aggregated donation statistics
  (lifetime and last-12-months totals, counts, first/last contribution dates, currency).
- `FinancialTypeResolver` — cached mapping of financial type name → integer ID via
  `resolve(string)`, `resolveMany(array)`, and `clearCache()`. Throws `ValidationException`
  when the type does not exist.
- Typed `ContributionApi`: `get(GetQuery)`, `getById(int)`,
  `recordOneTime(contactId, amount, currency, receiveDate, financialType, status, source, extra)`
  (resolves financial type name automatically), `create(array)` (low-level, caller supplies
  `financial_type_id`), `forContact(contactId)` (ordered by `receive_date DESC`),
  `totalsForContact(contactId, currency)` (two aggregate transport calls), `completedSince(date)`,
  `markCompleted(contributionId, trxnId, receivedDate)`, and `refund(contributionId, reason)`
  (updates status; optionally creates a Follow Up activity with the reason).
- `CiviCrmClient::contributions()` and `financialTypes()` entry points.
- `ValidationException::unknownFinancialType(string)` named constructor.

- Typed entity DTO `Note` under `src/Entity/`, representing a CiviCRM `Note` record with
  `id`, `entityTable`, `entityId`, `subject`, `note`, `privacy`, `modifiedDate`
  (`DateTimeImmutable`), and `contactIdCreator`. Parses `modified_date` from the APIv4
  `Y-m-d H:i:s` format; falls back to the Unix epoch when the field is absent or invalid.
- Typed `NoteApi`: `addToContact(contactId, note, subject, privacy)` (creates a note
  attached to a contact with `entity_table = 'civicrm_contact'`), `forContact(contactId)`
  (returns `Result<Note>` ordered by `modified_date DESC`), `delete(noteId)`,
  `get(GetQuery)` (escape hatch for arbitrary queries).
- `CiviCrmClient::notes()` entry point.

## [0.5.0] - 2026-06-08

### Added

- Typed entity DTOs `Relationship` and `RelationshipType` under `src/Entity/`, each
  exposing both directions of a relationship explicitly (`*AToB` / `*BToA`,
  `contact_id_a` / `contact_id_b`). `Relationship` parses `start_date` / `end_date`
  into `?DateTimeImmutable`.
- Typed `RelationshipTypeApi`: `all()` (memoized in memory), `byName()` (matches the
  forward **or** reverse name of the same type), and idempotent `ensureExists()` for
  schema seeding.
- Typed `RelationshipApi`: `create()` (type by name or id), `terminate()`,
  `forContact()` (returns relationships where the contact is side A **or** B), and
  `ofType()` (pre-filtered `GetQuery` for further refinement).
- `CiviCrmClient::relationships()` and `relationshipTypes()` entry points.

## [0.4.0] - 2026-06-07

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

[Unreleased]: https://github.com/woduda/civicrm-php/compare/v0.7.1...HEAD
[0.7.1]: https://github.com/woduda/civicrm-php/compare/v0.7.0...v0.7.1
[0.7.0]: https://github.com/woduda/civicrm-php/compare/v0.6.0...v0.7.0
[0.6.0]: https://github.com/woduda/civicrm-php/compare/v0.5.0...v0.6.0
[0.5.0]: https://github.com/woduda/civicrm-php/compare/v0.4.0...v0.5.0
[0.4.0]: https://github.com/woduda/civicrm-php/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/woduda/civicrm-php/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/woduda/civicrm-php/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/woduda/civicrm-php/releases/tag/v0.1.0
