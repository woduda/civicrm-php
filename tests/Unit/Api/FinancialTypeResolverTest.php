<?php

declare(strict_types=1);

use Woduda\CiviCRM\Api\FinancialTypeResolver;
use Woduda\CiviCRM\Exception\ValidationException;
use Woduda\CiviCRM\Result\ApiResponse;

// --- resolve ---

it('resolve sends FinancialType.get with name where clause', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 1, 'name' => 'Donation']]));

    (new FinancialTypeResolver($spy))->resolve('Donation');

    expect($spy->calls[0]['entity'])->toBe('FinancialType')
        ->and($spy->calls[0]['action'])->toBe('get')
        ->and($spy->calls[0]['params']['where'])->toBe([['name', '=', 'Donation']])
        ->and($spy->calls[0]['params']['limit'])->toBe(1);
});

it('resolve returns the integer ID from the API response', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 3, 'name' => 'Campaign Contribution']]));

    $id = (new FinancialTypeResolver($spy))->resolve('Campaign Contribution');

    expect($id)->toBe(3);
});

it('resolve throws ValidationException when type does not exist', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 0, []));

    expect(fn() => (new FinancialTypeResolver($spy))->resolve('Unknown Type'))
        ->toThrow(ValidationException::class, 'Financial type "Unknown Type" does not exist.');
});

it('resolve caches the result and does not call transport a second time', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 1, 'name' => 'Donation']]));

    $resolver = new FinancialTypeResolver($spy);
    $resolver->resolve('Donation');
    $resolver->resolve('Donation');

    expect($spy->calls)->toHaveCount(1);
});

it('resolve returns cached value on second call', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 1, 'name' => 'Donation']]));

    $resolver = new FinancialTypeResolver($spy);
    $resolver->resolve('Donation');
    $id = $resolver->resolve('Donation');

    expect($id)->toBe(1);
});

// --- resolveMany ---

it('resolveMany sends a single batch request for all missing names', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 2, [
        ['id' => 1, 'name' => 'Donation'],
        ['id' => 2, 'name' => 'Member Dues'],
    ]));

    (new FinancialTypeResolver($spy))->resolveMany(['Donation', 'Member Dues']);

    expect($spy->calls)->toHaveCount(1)
        ->and($spy->calls[0]['params']['where'])->toBe([['name', 'IN', ['Donation', 'Member Dues']]]);
});

it('resolveMany returns a name-to-id map', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 2, [
        ['id' => 1, 'name' => 'Donation'],
        ['id' => 2, 'name' => 'Member Dues'],
    ]));

    $result = (new FinancialTypeResolver($spy))->resolveMany(['Donation', 'Member Dues']);

    expect($result)->toBe(['Donation' => 1, 'Member Dues' => 2]);
});

it('resolveMany serves cached entries without transport calls', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 1, 'name' => 'Donation']]));
    $spy->queue(new ApiResponse(4, 1, [['id' => 2, 'name' => 'Member Dues']]));

    $resolver = new FinancialTypeResolver($spy);
    $resolver->resolve('Donation');

    $result = $resolver->resolveMany(['Donation', 'Member Dues']);

    expect($spy->calls)->toHaveCount(2)
        ->and($result)->toBe(['Donation' => 1, 'Member Dues' => 2]);
});

it('resolveMany throws ValidationException when any name does not exist', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 1, 'name' => 'Donation']]));

    expect(fn() => (new FinancialTypeResolver($spy))->resolveMany(['Donation', 'Ghost']))
        ->toThrow(ValidationException::class, 'Financial type "Ghost" does not exist.');
});

// --- clearCache ---

it('clearCache causes the next resolve to hit the transport again', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 1, 'name' => 'Donation']]));
    $spy->queue(new ApiResponse(4, 1, [['id' => 1, 'name' => 'Donation']]));

    $resolver = new FinancialTypeResolver($spy);
    $resolver->resolve('Donation');
    $resolver->clearCache();
    $resolver->resolve('Donation');

    expect($spy->calls)->toHaveCount(2);
});
