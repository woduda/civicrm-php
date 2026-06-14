<?php

declare(strict_types=1);

use Woduda\CiviCRM\Exception\ApiException;
use Woduda\CiviCRM\Exception\AuthenticationException;
use Woduda\CiviCRM\Exception\RateLimitException;
use Woduda\CiviCRM\Exception\TransportException;
use Woduda\CiviCRM\Exception\ValidationException;
use Woduda\CiviCRM\Retry\ExponentialBackoff;

it('grows the delay exponentially without jitter', function (): void {
    $backoff = new ExponentialBackoff(maxAttempts: 5, baseDelayMs: 200, multiplier: 2.0, jitter: false);

    expect($backoff->delayMs(1))->toBe(200)
        ->and($backoff->delayMs(2))->toBe(400)
        ->and($backoff->delayMs(3))->toBe(800)
        ->and($backoff->delayMs(4))->toBe(1600);
});

it('caps the delay at maxDelayMs', function (): void {
    $backoff = new ExponentialBackoff(maxAttempts: 10, baseDelayMs: 1000, multiplier: 3.0, maxDelayMs: 5000, jitter: false);

    expect($backoff->delayMs(1))->toBe(1000)
        ->and($backoff->delayMs(2))->toBe(3000)
        ->and($backoff->delayMs(3))->toBe(5000)
        ->and($backoff->delayMs(9))->toBe(5000);
});

it('keeps jittered delays within [0, cappedBase]', function (): void {
    $backoff = new ExponentialBackoff(maxAttempts: 10, baseDelayMs: 200, multiplier: 2.0, jitter: true);

    for ($i = 0; $i < 200; $i++) {
        $delay = $backoff->delayMs(3);

        expect($delay)->toBeGreaterThanOrEqual(0)
            ->and($delay)->toBeLessThanOrEqual(800);
    }
});

it('honors Retry-After over the computed back-off', function (): void {
    $backoff = new ExponentialBackoff(baseDelayMs: 200, jitter: true);

    $delay = $backoff->delayMs(1, new RateLimitException('rate limited', 0, 429, retryAfterSeconds: 2));

    expect($delay)->toBe(2000);
});

it('caps Retry-After at maxDelayMs', function (): void {
    $backoff = new ExponentialBackoff(maxDelayMs: 5000, jitter: false);

    $delay = $backoff->delayMs(1, new RateLimitException('rate limited', 0, 429, retryAfterSeconds: 120));

    expect($delay)->toBe(5000);
});

it('falls back to back-off when RateLimitException has no Retry-After', function (): void {
    $backoff = new ExponentialBackoff(baseDelayMs: 200, jitter: false);

    $delay = $backoff->delayMs(2, new RateLimitException('rate limited', 0, 429));

    expect($delay)->toBe(400);
});

it('stops retrying once maxAttempts is reached', function (): void {
    $backoff = new ExponentialBackoff(maxAttempts: 3);

    expect($backoff->shouldRetry(2, new TransportException('boom')))->toBeTrue()
        ->and($backoff->shouldRetry(3, new TransportException('boom')))->toBeFalse()
        ->and($backoff->shouldRetry(4, new TransportException('boom')))->toBeFalse();
});

it('decides retryability per exception type', function (Throwable $e, bool $expected): void {
    $backoff = new ExponentialBackoff(maxAttempts: 5);

    expect($backoff->shouldRetry(1, $e))->toBe($expected);
})->with([
    'transport error' => [new TransportException('network down'), true],
    'rate limit' => [new RateLimitException('429', 0, 429, 1), true],
    'server error 500' => [new ApiException('boom', 0, 500), true],
    'server error 503' => [new ApiException('unavailable', 0, 503), true],
    'server error 599' => [new ApiException('edge', 0, 599), true],
    'client error 400' => [new ApiException('bad request', 0, 400), false],
    'client error 404' => [new ApiException('not found', 0, 404), false],
    'authentication 401' => [new AuthenticationException('unauthorized', 0, 401), false],
    'validation' => [ValidationException::unknownCountry('XX'), false],
    'api error without status' => [new ApiException('mystery'), false],
    'unrelated throwable' => [new RuntimeException('???'), false],
]);
