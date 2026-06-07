<?php

declare(strict_types=1);

use Woduda\CiviCRM\Entity\Contact;
use Woduda\CiviCRM\Result\ApiResponse;
use Woduda\CiviCRM\Result\Result;
use Woduda\CiviCRM\Result\TypedResult;

it('hydrates a fixture into Result of Contact DTOs', function (): void {
    $payload = fixtureApiPayload('contact-single.json');
    $response = new ApiResponse(4, $payload['count'], $payload['values']);

    $result = TypedResult::hydrate(Result::fromApiResponse($response), Contact::class);

    expect($result)->toBeInstanceOf(Result::class)
        ->and($result->count())->toBe(1)
        ->and($result->first())->toBeInstanceOf(Contact::class)
        ->and($result->first()?->id)->toBe(42)
        ->and($result->first()?->displayName)->toBe('Jane Doe')
        ->and($result->first()?->email)->toBe('jane@example.org');
});

it('skips non-array rows during hydration', function (): void {
    $raw = new Result(['not-a-row', ['id' => 1]], 2);
    $result = TypedResult::hydrate($raw, Contact::class);

    expect($result->values)->toHaveCount(1)
        ->and($result->first())->toBeInstanceOf(Contact::class)
        ->and($result->first()?->id)->toBe(1)
        ->and($result->count())->toBe(2);
});
