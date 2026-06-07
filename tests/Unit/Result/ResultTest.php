<?php

declare(strict_types=1);

use Woduda\CiviCRM\Result\ApiResponse;
use Woduda\CiviCRM\Result\Result;

it('iterates over values', function (): void {
    $result = new Result([1, 2, 3], 3);

    expect(iterator_to_array($result))->toBe([1, 2, 3]);
});

it('reports count from the server-reported total', function (): void {
    $result = new Result([['id' => 1]], 42);

    expect($result->count())->toBe(42)
        ->and(count($result))->toBe(42);
});

it('returns the first value or null', function (): void {
    $result = new Result(['a', 'b'], 2);
    $empty = new Result([], 0);

    expect($result->first())->toBe('a')
        ->and($empty->first())->toBeNull();
});

it('reports isEmpty based on count', function (): void {
    expect((new Result([], 0))->isEmpty())->toBeTrue()
        ->and((new Result([1], 1))->isEmpty())->toBeFalse();
});

it('maps values while preserving the original count', function (): void {
    $result = new Result([1, 2], 10);
    $mapped = $result->map(fn(int $n): string => "n{$n}");

    expect($mapped->values)->toBe(['n1', 'n2'])
        ->and($mapped->count())->toBe(10);
});

it('filters values and updates the count', function (): void {
    $result = new Result([1, 2, 3, 4], 4);
    $filtered = $result->filter(fn(int $n): bool => $n % 2 === 0);

    expect($filtered->values)->toBe([2, 4])
        ->and($filtered->count())->toBe(2);
});

it('is constructed from an ApiResponse', function (): void {
    $response = new ApiResponse(4, 2, [['id' => 1], ['id' => 2]]);
    $result = Result::fromApiResponse($response);

    expect($result->values)->toBe([['id' => 1], ['id' => 2]])
        ->and($result->count())->toBe(2);
});

it('re-indexes associative values array from a non-list API response', function (): void {
    $response = new ApiResponse(4, 1, ['first' => ['id' => 1, 'name' => 'Jane']]);
    $result = Result::fromApiResponse($response);

    expect($result->values)->toBe([['id' => 1, 'name' => 'Jane']])
        ->and($result->count())->toBe(1);
});

it('skips non-array rows and does not break iteration', function (): void {
    $response = new ApiResponse(4, 2, ['scalar-skip', ['id' => 1]]);
    $result = Result::fromApiResponse($response);

    expect($result->values)->toBe([['id' => 1]])
        ->and($result->count())->toBe(2);
});

it('preserves a server-reported count of zero', function (): void {
    $response = new ApiResponse(4, 0, []);
    $result = Result::fromApiResponse($response);

    expect($result->count())->toBe(0);
});

it('clamps negative server-reported counts to zero', function (): void {
    $result = Result::fromApiResponse(new ApiResponse(4, -1, []));

    expect($result->count())->toBe(0);
});
