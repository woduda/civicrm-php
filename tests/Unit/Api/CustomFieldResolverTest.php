<?php

declare(strict_types=1);

use Woduda\CiviCRM\Api\CustomFieldResolver;
use Woduda\CiviCRM\Exception\ValidationException;
use Woduda\CiviCRM\Result\ApiResponse;

it('resolve returns the dotted API key when the custom field exists', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 11, 'name' => 'volunteer_status']]));

    $key = (new CustomFieldResolver($spy))->resolve('Wolontariat', 'volunteer_status');

    expect($key)->toBe('Wolontariat.volunteer_status')
        ->and($spy->calls)->toHaveCount(1)
        ->and($spy->calls[0]['entity'])->toBe('CustomField')
        ->and($spy->calls[0]['action'])->toBe('get')
        ->and($spy->calls[0]['params']['where'])->toBe([
            ['custom_group_id.name', '=', 'Wolontariat'],
            ['name', '=', 'volunteer_status'],
        ]);
});

it('resolve throws ValidationException when the custom field does not exist', function (): void {
    $spy = new SpyTransport(); // default empty response → field not found

    expect(fn() => (new CustomFieldResolver($spy))->resolve('Wolontariat', 'nonexistent'))
        ->toThrow(ValidationException::class, 'Custom field "Wolontariat.nonexistent" does not exist.');
});

it('resolve caches the result and does not issue a second transport call', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 11, 'name' => 'volunteer_status']]));

    $resolver = new CustomFieldResolver($spy);
    $first = $resolver->resolve('Wolontariat', 'volunteer_status');
    $second = $resolver->resolve('Wolontariat', 'volunteer_status');

    expect($first)->toBe('Wolontariat.volunteer_status')
        ->and($second)->toBe('Wolontariat.volunteer_status')
        ->and($spy->calls)->toHaveCount(1); // only one transport call
});

it('resolve queries separately for different fields in the same group', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 11, 'name' => 'field_a']]));
    $spy->queue(new ApiResponse(4, 1, [['id' => 12, 'name' => 'field_b']]));

    $resolver = new CustomFieldResolver($spy);
    $resolver->resolve('Group', 'field_a');
    $resolver->resolve('Group', 'field_b');

    expect($spy->calls)->toHaveCount(2);
});
