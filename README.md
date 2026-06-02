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
- [Entities & actions](#entities--actions)
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
use Woduda\CiviCRM\Client;
use Woduda\CiviCRM\Config;

$client = new Client(new Config(
    baseUrl: 'https://example.org/civicrm/ajax/api4/',
    apiKey: 'your-api-key',
));

$response = $client->contacts()->get([
    'where' => [['contact_type', '=', 'Individual']],
    'limit' => 25,
]);

foreach ($response->values as $contact) {
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
    apiKey: 'your-api-key',
);
```

The client sends `Authorization: Bearer {apiKey}` together with the required
`X-Requested-With: XMLHttpRequest` header on every request.

### Injecting your own HTTP client

Discovery is convenient, but you can inject any PSR-18 client (e.g. one
configured with timeouts, retries or a mock in tests):

```php
use Woduda\CiviCRM\Client;
use Woduda\CiviCRM\Config;

$client = new Client(
    config: $config,
    httpClient: $myPsr18Client,        // optional
    requestFactory: $myRequestFactory, // optional
    streamFactory: $myStreamFactory,   // optional
);
```

## Entities & actions

Each entity is reached through an accessor on the client; every accessor exposes
the standard APIv4 actions (`get`, `create`, `update`, `save`, `delete`,
`replace`, `getActions`, `getFields`):

```php
$client->contacts();
$client->emails();
$client->phones();
$client->addresses();
$client->activities();
$client->events();
$client->participants();
$client->contributions();
```

```php
// Create
$client->contacts()->create([
    'values' => ['contact_type' => 'Individual', 'first_name' => 'Jane', 'last_name' => 'Doe'],
]);

// Update
$client->contacts()->update([
    'values' => ['first_name' => 'Janet'],
    'where' => [['id', '=', 42]],
]);

// Delete
$client->contacts()->delete([
    'where' => [['id', '=', 42]],
]);
```

Hand-writing the `params` array works, but the typed builders below are safer.

## Query builder (`GetQuery`)

`GetQuery` is an immutable builder: every method returns a **new** instance and
`toParams()` renders the APIv4 `params` array. Feed it into any `get` call.

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

$response = $client->contacts()->get($query->toParams());
```

`toParams()` only emits keys you actually set, so the example above produces:

```php
[
    'select'  => ['id', 'display_name', 'email_primary.email'],
    'where'   => [['contact_type', '=', 'Individual'], ['id', 'IN', [1, 2, 3]]],
    'orderBy' => ['display_name' => 'DESC'],
    'limit'   => 50,
    'offset'  => 100,
]
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

`ActionRequest` models a single write action with named constructors. Execute it
by sending `"{$entity}/{$action}"` with its rendered params:

```php
use Woduda\CiviCRM\Query\ActionRequest;

$request = ActionRequest::create('Contact', [
    'contact_type' => 'Individual',
    'first_name' => 'Jane',
]);

$client->sendRequest("{$request->entity}/{$request->action}", $request->toParams());
```

```php
ActionRequest::update('Contact', ['first_name' => 'Janet'], [['id', '=', 42]]);
ActionRequest::save('Contact', [['first_name' => 'A'], ['first_name' => 'B']]); // bulk upsert
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
        'email' => 'jane@example.org',
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

## Responses

Successful calls return an immutable `ApiResponse`:

```php
$response = $client->contacts()->get($query->toParams());

$response->count;   // int — number of records reported by CiviCRM
$response->values;  // array — the returned records
$response->version; // int — APIv4 version
```

## Error handling

Every exception thrown by the library implements `CivicrmException`, so you can
catch the whole library with one type:

```php
use Woduda\CiviCRM\Exception\ApiException;
use Woduda\CiviCRM\Exception\CivicrmException;
use Woduda\CiviCRM\Exception\ValidationException;

try {
    $client->contacts()->get($query->toParams());
} catch (ApiException $e) {
    // HTTP 4xx/5xx from CiviCRM: $e->getMessage() / $e->getCode()
} catch (ValidationException $e) {
    // Invalid builder input (e.g. a bad orderBy direction)
} catch (CivicrmException $e) {
    // Anything else originating from this library
}
```

Transport-level failures surface as PSR-18 `Psr\Http\Client\ClientExceptionInterface`.
