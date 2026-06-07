<?php

declare(strict_types=1);

use Woduda\CiviCRM\Api\EmailApi;
use Woduda\CiviCRM\Entity\Email;
use Woduda\CiviCRM\Result\ApiResponse;

it('preserves Email generic types through Result helpers', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [[
        'id' => 101,
        'contact_id' => 42,
        'email' => 'jane@example.org',
        'is_primary' => true,
    ]]));

    $result = (new EmailApi($spy))->forContact(42);
    $first = $result->first();
    $mapped = $result->map(fn(Email $email): string => $email->email);
    $filtered = $result->filter(fn(Email $email): bool => $email->isPrimary);

    expect($first)->toBeInstanceOf(Email::class)
        ->and($first?->email)->toBe('jane@example.org')
        ->and($mapped->first())->toBe('jane@example.org')
        ->and($filtered->first()?->isPrimary)->toBeTrue();
});
