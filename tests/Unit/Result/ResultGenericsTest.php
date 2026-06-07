<?php

declare(strict_types=1);

use Woduda\CiviCRM\Api\ContactApi;
use Woduda\CiviCRM\Api\CustomFieldResolver;
use Woduda\CiviCRM\Entity\Contact;
use Woduda\CiviCRM\Query\GetQuery;
use Woduda\CiviCRM\Result\ApiResponse;

it('preserves Contact generic types through Result helpers', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 42, 'display_name' => 'Jane Doe']]));

    $result = (new ContactApi($spy, new CustomFieldResolver($spy)))->get(GetQuery::new());
    $first = $result->first();
    $mapped = $result->map(fn(Contact $contact): int => $contact->id ?? 0);
    $filtered = $result->filter(fn(Contact $contact): bool => $contact->displayName !== null);

    expect($first)->toBeInstanceOf(Contact::class)
        ->and($first?->id)->toBe(42)
        ->and($mapped->first())->toBe(42)
        ->and($filtered->first()?->displayName)->toBe('Jane Doe');
});
