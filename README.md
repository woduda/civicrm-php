# civicrm-php

A PSR-18 compatible client for the **CiviCRM REST APIv4**. Framework-agnostic,
immutable and fully typed — it mirrors the ergonomics of modern API SDKs and
acts as a typed transport over APIv4 (it is **not** an ORM).

CiviCRM APIv4 REST docs: <https://docs.civicrm.org/dev/en/latest/api/v4/rest/>

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Quickstart](#quickstart)
- [Configuration & authentication](#configuration--authentication)
- [Generic entity API (`CiviCrmClient`)](#generic-entity-api-civicrmclient)
    - [Typed entity shortcuts](#typed-entity-shortcuts)
    - [Arbitrary entities](#arbitrary-entities)
    - [CRUD methods](#crud-methods)
    - [Escape hatch (`raw`)](#escape-hatch-raw)
- [Contact API](#contact-api)
- [Activity API](#activity-api)
- [Tag API](#tag-api)
- [Group API](#group-api)
- [Custom fields](#custom-fields)
- [Query builder (`GetQuery`)](#query-builder-getquery)
    - [Operators](#operators)
    - [AND / OR grouping](#and--or-grouping)
- [Write actions (`ActionRequest`)](#write-actions-actionrequest)
- [Chained calls (`ChainBuilder`)](#chained-calls-chainbuilder)
- [Responses](#responses)
- [Error handling](#error-handling)

## Requirements

- PHP >= 8.3
- Any [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP client + PSR-17 factories
  (discovered automatically via [php-http/discovery](https://github.com/php-http/discovery))
- The CiviCRM [`authx`](https://docs.civicrm.org/dev/en/latest/framework/authx/)
  extension for bearer-token auth

## Installation

```bash
composer require woduda/civicrm-php
```

No concrete HTTP client is bundled. Install whichever PSR-18 implementation you
prefer and it will be discovered automatically:

```bash
composer require guzzlehttp/guzzle
# or
composer require symfony/http-client nyholm/psr7
```

## Quickstart

```php
use Woduda\CiviCRM\CiviCrmClient;
use Woduda\CiviCRM\Config;
use Woduda\CiviCRM\Query\GetQuery;
use Woduda\CiviCRM\Query\Operator;

$client = CiviCrmClient::create(new Config(
    baseUrl: 'https://example.org/civicrm/ajax/api4/',
    apiKey:  'your-api-key',
));

$contacts = $client->contacts()->get(
    GetQuery::new()
        ->select('id', 'display_name', 'email_primary.email')
        ->where('contact_type', Operator::Equals, 'Individual')
        ->orderBy('display_name')
        ->limit(25),
);

foreach ($contacts as $contact) {
    echo $contact->displayName, PHP_EOL;
}
```

## Configuration & authentication

`Config` is an immutable value object. The `baseUrl` must point at the APIv4
endpoint and end with a trailing slash:

```php
use Woduda\CiviCRM\Config;

$config = new Config(
    baseUrl: 'https://example.org/civicrm/ajax/api4/',
    apiKey:  'your-api-key',
);
```

The client sends `Authorization: Bearer {apiKey}` together with the required
`X-Requested-With: XMLHttpRequest` header on every request.

`CiviCrmClient::create()` auto-discovers the installed PSR-18 client:

```php
use Woduda\CiviCRM\CiviCrmClient;

$client = CiviCrmClient::create($config);
```

### Injecting your own HTTP client

Pass any PSR-18 client (e.g. one configured with timeouts, retries, or a mock
in tests) via `CiviCrmClient`'s constructor:

```php
use Woduda\CiviCRM\CiviCrmClient;
use Woduda\CiviCRM\Http\Transport;

$client = new CiviCrmClient(
    new Transport(new \Woduda\CiviCRM\Client($config, $myPsr18Client)),
);
```

## Generic entity API (`CiviCrmClient`)

`CiviCrmClient` is the primary entry point. It wraps a `TransportInterface` and
exposes a fluent API over any CiviCRM entity. All CRUD methods accept typed
builder objects directly — no `.toParams()` glue is needed.

### Typed entity shortcuts

```php
$client->contacts();      // ContactApi  — typed Contact API with upsert, tag/group helpers
$client->activities();    // ActivityApi — typed Activity API with logForContact helper
$client->tags();          // TagApi      — get-or-create tags, tag a contact
$client->groups();        // GroupApi    — get-or-create groups, manage membership
$client->emails();        // EmailApi    — typed Email API for contact sub-entity
$client->phones();        // PhoneApi    — typed Phone API for contact sub-entity
$client->addresses();    // AddressApi  — typed Address API for contact sub-entity
```

All typed APIs expose `getFields()` and `getActions()` in addition to their
domain methods. See [Contact API](#contact-api), [Activity API](#activity-api),
[Tag API](#tag-api), [Group API](#group-api), [Email API](#email-api),
[Phone API](#phone-api), and [Address API](#address-api) for the full method reference.

### Arbitrary entities

Use `entity(string)` for any entity not covered by a shortcut:

```php
$client->entity('Relationship')->get(GetQuery::new()->limit(10));
$client->entity('OptionValue')->create(['label' => 'VIP', 'option_group_id' => 1]);
```

### CRUD methods

Typed entity APIs (`contacts()`, `activities()`) return a `Result` of typed DTOs.
`GenericApi` (`entity()`) still returns the raw values array.

```php
// Read — Result<Contact>
$contacts = $client->contacts()->get(
    GetQuery::new()->where('last_name', Operator::Equals, 'Smith')->limit(50),
);

// Create — Result<Contact>
$new = $client->contacts()->create([
    'contact_type' => 'Individual',
    'first_name'   => 'Jane',
    'last_name'    => 'Doe',
]);

// Update — $where accepts a GetQuery or a raw APIv4 where array
$client->contacts()->update(
    ['first_name' => 'Janet'],
    GetQuery::new()->where('id', Operator::Equals, 42),
);
// or:
$client->contacts()->update(['first_name' => 'Janet'], [['id', '=', 42]]);

// Save (bulk upsert)
$client->contacts()->save([
    ['id' => 1, 'do_not_email' => true],
    ['id' => 2, 'do_not_email' => true],
]);

// Delete — $where accepts a GetQuery or a raw APIv4 where array
$client->contacts()->delete(GetQuery::new()->where('id', Operator::Equals, 42));
// or:
$client->contacts()->delete([['id', '=', 42]]);

// Metadata
$fields  = $client->contacts()->getFields();   // array of field definitions
$actions = $client->contacts()->getActions();  // array of available actions
```

### Escape hatch (`raw`)

For any action not exposed by typed methods, call `raw()` directly:

```php
$result = $client->raw('Contact', 'merge', [
    'main_id'  => 1,
    'other_id' => 2,
]);
```

## Contact API

`$client->contacts()` returns a `ContactApi` with domain-level helpers on top of
basic CRUD:

```php
$contacts = $client->contacts();

// Read
$all   = $contacts->get(GetQuery::new()->where('contact_type', Operator::Equals, 'Individual'));
$one   = $contacts->getById(42);         // returns Contact|null

// Write
$new   = $contacts->create(['contact_type' => 'Individual', 'first_name' => 'Jane']);
$upd   = $contacts->update(42, ['last_name' => 'Doe']);
```

### Email upsert

```php
// Finds by email_primary.email; updates if found, creates (with email merged) if not.
// ⚠ Not atomic — see source docblock for details.
$contact = $contacts->upsertByEmail('jane@example.org', [
    'first_name'   => 'Jane',
    'contact_type' => 'Individual',
]);
```

### Tag assignment

```php
// Resolves tag names to IDs (creates missing ones) then saves all at once.
// Idempotent — safe to call multiple times.
$contacts->withTags(42, ['Donor', 'VIP']);
```

### Group membership

```php
// Resolves group titles to IDs (creates missing ones) then saves memberships.
$contacts->addToGroups(42, ['Newsletter', 'Volunteers']);
```

### Custom fields

```php
// Validates each field name via CustomFieldResolver, then runs a single update.
// Throws ValidationException if a field doesn't exist.
$contacts->setCustomFields(42, 'Wolontariat', [
    'volunteer_status' => 'active',
    'start_date'       => '2024-01-01',
]);
```

### Primary email, phone, and address shortcuts

Convenience methods that update the primary sub-entity record, or create one
with `is_primary = true` when none exists:

```php
$email = $contacts->updatePrimaryEmail(42, 'jane@example.org');

$phone = $contacts->updatePrimaryPhone(42, '+48123456789', 'Mobile');

$address = $contacts->updatePrimaryAddress(42, AddressData::fromArray([
    'street_address' => 'Main St 1',
    'city'           => 'Warsaw',
    'postal_code'    => '00-001',
    'country'        => 'PL',
]));
```

These delegate to `EmailApi`, `PhoneApi`, and `AddressApi` — use those directly
when you need full control (multiple locations, billing flags, etc.).

## Activity API

```php
$activities = $client->activities();

// Generic create
$activities->create([
    'activity_type_id.name' => 'Meeting',
    'subject'               => 'Kickoff',
]);

// Convenience: link to a contact, default status = Completed
$activities->logForContact(42, 'Phone Call', ['subject' => 'Intake call', 'duration' => 30]);

// Returns a pre-filtered GetQuery — chain .select(), .limit() etc. as needed
$query   = $activities->forContact(42)->select('id', 'subject')->limit(20);
$results = $activities->get($query);
```

## Tag API

```php
$tags = $client->tags();

// Returns ID of existing tag, or creates it and returns the new ID
$tagId = $tags->ensureExists('VIP');

// Ensures the tag exists, then creates an EntityTag (idempotent)
$tags->tagContact(42, 'VIP');
```

## Group API

```php
$groups = $client->groups();

// Returns ID of existing group, or creates it and returns the new ID
$groupId = $groups->ensureExists('Newsletter');

// Add / remove membership (removeContact updates status → 'Removed' for audit trail)
$groups->addContact(42, $groupId);
$groups->removeContact(42, $groupId);
```

## Email API

Email, Phone, and Address are separate CiviCRM entities with their own IDs.
Each typed sub-entity API returns `Result<DTO>` and provides contact-scoped helpers.

```php
$emails = $client->emails();

// All emails for a contact (primary first)
$all = $emails->forContact(42);

// Primary email, or null
$primary = $emails->primary(42);

// Mark as primary — CiviCRM unsets is_primary on other emails for that contact
$emails->setPrimary(101);

// Add / remove
$email = $emails->add(42, 'jane@example.org', 'Home', isPrimary: true);
$emails->remove(101);
```

## Phone API

```php
$phones = $client->phones();

$all     = $phones->forContact(42);
$primary = $phones->primary(42);
$phones->setPrimary(201);
$phone   = $phones->add(42, '+48123456789', 'Mobile', 'Home', isPrimary: true);
$phones->remove(201);
```

## Address API

```php
$addresses = $client->addresses();

$all     = $addresses->forContact(42);
$primary = $addresses->primary(42);
$addresses->setPrimary(301);

// addFromData resolves country (ISO-2 or name) and state/province via Country.get
$address = $addresses->addFromData(42, AddressData::fromArray([
    'street_address' => 'Main St 1',
    'city'           => 'Warsaw',
    'postal_code'    => '00-001',
    'country'        => 'PL',
    'state_province' => 'Mazovia',
]), isPrimary: true);

$addresses->remove(301);
```

## Custom fields

In CiviCRM APIv4, custom fields are addressed as `"GroupName.field_name"` in both
`select` arrays and `values` maps. `CustomFieldResolver` validates that a given
combination exists and caches the result per instance:

```php
use Woduda\CiviCRM\Api\CustomFieldResolver;
use Woduda\CiviCRM\Http\Transport;

$resolver = new CustomFieldResolver(Transport::createDefault($config));

// Returns 'Wolontariat.volunteer_status' if the field exists
$key = $resolver->resolve('Wolontariat', 'volunteer_status');

// Throws ValidationException if the field doesn't exist
$resolver->resolve('Wolontariat', 'nonexistent'); // ❌
```

`ContactApi::setCustomFields()` uses a `CustomFieldResolver` internally — you do
not need to instantiate it yourself when going through `$client->contacts()`.

The resolver is entity-agnostic: custom groups extending Email, Phone, or Address
(if configured in CiviCRM) use the same `"GroupName.field_name"` notation.
Validate with `resolve()` and pass the dotted key in `GetQuery::select()` or
create/update values.

## Query builder (`GetQuery`)

`GetQuery` is an immutable builder: every method returns a **new** instance.
Pass a `GetQuery` directly to any `get()` or `delete()` call — there is no need
to call `.toParams()` when using `CiviCrmClient`.

```php
use Woduda\CiviCRM\Query\GetQuery;
use Woduda\CiviCRM\Query\Operator;

$query = GetQuery::new()
    ->select('id', 'display_name', 'email_primary.email')
    ->where('contact_type', Operator::Equals, 'Individual')
    ->whereIn('id', [1, 2, 3])
    ->orderBy('display_name', 'DESC')
    ->limit(50)
    ->offset(100);

$contacts = $client->contacts()->get($query);
```

`toParams()` is available when you need the raw APIv4 params array:

```php
$query->toParams();
// [
//     'select'  => ['id', 'display_name', 'email_primary.email'],
//     'where'   => [['contact_type', '=', 'Individual'], ['id', 'IN', [1, 2, 3]]],
//     'orderBy' => ['display_name' => 'DESC'],
//     'limit'   => 50,
//     'offset'  => 100,
// ]
```

Other helpers: `addSelect()`, `whereNull()`, `groupBy()`, `having()`.

### Operators

`Operator` is a backed enum covering the APIv4 operators:

| Enum case                        | APIv4                     |
| -------------------------------- | ------------------------- |
| `Equals` / `NotEquals`           | `=` / `!=`                |
| `GreaterThan` / `LessThan`       | `>` / `<`                 |
| `GreaterOrEqual` / `LessOrEqual` | `>=` / `<=`               |
| `Like` / `NotLike`               | `LIKE` / `NOT LIKE`       |
| `In` / `NotIn`                   | `IN` / `NOT IN`           |
| `Between` / `NotBetween`         | `BETWEEN` / `NOT BETWEEN` |
| `IsNull` / `IsNotNull`           | `IS NULL` / `IS NOT NULL` |
| `Contains`                       | `CONTAINS`                |

Unary operators omit the value automatically:

```php
GetQuery::new()->where('deleted_date', Operator::IsNull)->toParams();
// ['where' => [['deleted_date', 'IS NULL']]]
```

### AND / OR grouping

`where()` adds an `AND` condition; `orWhere()` groups with the **previous**
clause into an explicit APIv4 `OR` group (Laravel-style), and consecutive
`orWhere()` calls extend that group:

```php
GetQuery::new()
    ->where('first_name', Operator::Equals, 'Jane')
    ->orWhere('first_name', Operator::Equals, 'John')
    ->where('is_deleted', Operator::Equals, 0)
    ->toParams();
// where => [
//   ['OR', [['first_name', '=', 'Jane'], ['first_name', '=', 'John']]],
//   ['is_deleted', '=', 0],
// ]
```

## Write actions (`ActionRequest`)

`ActionRequest` models a single write action as an immutable value object with
named constructors. You can build it explicitly for complex operations, or pass
its rendered params to `raw()`:

```php
use Woduda\CiviCRM\Query\ActionRequest;

// Build and introspect before sending
$request = ActionRequest::create('Contact', [
    'contact_type' => 'Individual',
    'first_name'   => 'Jane',
]);

// Send via raw() for full control
$client->raw($request->entity, $request->action, $request->toParams());
```

```php
ActionRequest::update('Contact', ['first_name' => 'Janet'], [['id', '=', 42]]);
ActionRequest::save('Contact', [['first_name' => 'A'], ['first_name' => 'B']]);
ActionRequest::delete('Contact', [['id', '=', 42]]);
```

## Chained calls (`ChainBuilder`)

APIv4 chaining runs follow-up calls for each result of the primary call;
sub-calls reference the parent record via `$id`-style placeholders.

Attach a sub-call directly to an `ActionRequest`:

```php
use Woduda\CiviCRM\Query\ActionRequest;

$request = ActionRequest::create('Contact', ['first_name' => 'Jane'])
    ->withChain('email', ActionRequest::create('Email', [
        'contact_id' => '$id',
        'email'      => 'jane@example.org',
    ]));

// chain => ['email' => ['Email', 'create', ['values' => [...]]]]
```

Or assemble several entries with `ChainBuilder` and merge them in:

```php
use Woduda\CiviCRM\Query\ChainBuilder;
use Woduda\CiviCRM\Query\GetQuery;
use Woduda\CiviCRM\Query\Operator;

$chain = ChainBuilder::new()
    ->create('email', 'Email', ['contact_id' => '$id', 'email' => 'jane@example.org'])
    ->get('activities', 'Activity', GetQuery::new()->where('source_contact_id', Operator::Equals, '$id'));

$request = ActionRequest::create('Contact', ['first_name' => 'Jane'])
    ->withChainBuilder($chain);
```

> A `GetQuery` passed to `ActionRequest::withChain()` is chained as a `get` on the
> parent request's entity. To chain a `get` on a _different_ entity, use
> `ChainBuilder::get()` / `ChainBuilder::add()`.

Execute a chained request via `raw()`:

```php
$result = $client->raw($request->entity, $request->action, $request->toParams());
```

## Responses

**`CiviCrmClient` methods** (`get`, `create`, `update`, `save`, `delete`,
`getFields`, `getActions`, `raw`) return the values array directly:

```php
$contacts = $client->contacts()->get(GetQuery::new()->limit(5));
// [['id' => 1, 'display_name' => 'Jane Doe'], ...]
```

**The low-level transport** returns an immutable `ApiResponse` value object when
you need the full response metadata:

```php
use Woduda\CiviCRM\Http\Transport;

$transport = Transport::createDefault($config);
$response  = $transport->send('Contact', 'get', ['limit' => 5]);

$response->values;  // array — returned records
$response->count;   // int — number of records reported by CiviCRM
$response->version; // int — APIv4 version (4)
```

## Error handling

Every exception thrown by the library implements `CivicrmException`, so you can
catch the whole library with one type:

```php
use Woduda\CiviCRM\Exception\ApiException;
use Woduda\CiviCRM\Exception\CivicrmException;
use Woduda\CiviCRM\Exception\ValidationException;

try {
    $client->contacts()->get(GetQuery::new()->limit(10));
} catch (ApiException $e) {
    // HTTP 4xx/5xx from CiviCRM: $e->getMessage() / $e->getCode()
} catch (ValidationException $e) {
    // Invalid builder input (e.g. a bad orderBy direction)
} catch (CivicrmException $e) {
    // Anything else originating from this library
}
```

Transport-level failures surface as PSR-18 `Psr\Http\Client\ClientExceptionInterface`.
