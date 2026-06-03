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
    echo $contact['display_name'], PHP_EOL;
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
$client->contacts();      // GenericApi for Contact
$client->activities();    // GenericApi for Activity
$client->tags();          // GenericApi for Tag
$client->groups();        // GenericApi for Group
```

### Arbitrary entities

Use `entity(string)` for any entity not covered by a shortcut:

```php
$client->entity('Relationship')->get(GetQuery::new()->limit(10));
$client->entity('OptionValue')->create(['label' => 'VIP', 'option_group_id' => 1]);
```

### CRUD methods

All CRUD methods return the values array (the equivalent of `ApiResponse->values`):

```php
// Read
$contacts = $client->contacts()->get(
    GetQuery::new()->where('last_name', Operator::Equals, 'Smith')->limit(50),
);

// Create — returns the created record(s)
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
