<?php

declare(strict_types=1);

use Woduda\CiviCRM\Exception\ApiErrorException;
use Woduda\CiviCRM\Exception\ApiException;
use Woduda\CiviCRM\Exception\RateLimitException;

it('resolves the legacy ApiException name to the renamed ApiErrorException', function (): void {
    expect((new ReflectionClass(ApiException::class))->getName())->toBe(ApiErrorException::class);
});

it('still catches an ApiErrorException via the legacy ApiException name', function (): void {
    $caught = false;

    try {
        throw new ApiErrorException('boom', 0, 500);
    } catch (ApiException $e) {
        $caught = true;
        expect($e->httpStatus)->toBe(500);
    }

    expect($caught)->toBeTrue();
});

it('still recognises subclasses as the legacy ApiException', function (): void {
    expect(new RateLimitException('rate limited', 0, 429, 5))->toBeInstanceOf(ApiException::class);
});
