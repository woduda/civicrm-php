<?php

declare(strict_types=1);

use Nyholm\Psr7\Response;
use Woduda\CiviCRM\Exception\ApiException;
use Woduda\CiviCRM\Exception\AuthenticationException;
use Woduda\CiviCRM\Exception\RateLimitException;

it('maps error_message and error_code from the body', function (): void {
    $body = (string) json_encode([
        'error_message' => 'Record not found',
        'error_code' => 404,
    ]);

    $exception = ApiException::fromResponse(new Response(404, [], $body));

    expect($exception->getMessage())->toBe('Record not found')
        ->and($exception->getCode())->toBe(404);
});

it('casts a numeric string error_code to int', function (): void {
    $body = (string) json_encode([
        'error_message' => 'Bad request',
        'error_code' => '400',
    ]);

    $exception = ApiException::fromResponse(new Response(400, [], $body));

    expect($exception->getCode())->toBe(400);
});

it('uses defaults when fields are missing', function (): void {
    $exception = ApiException::fromResponse(new Response(500, [], '{}'));

    expect($exception->getMessage())->toBe('Unknown Api error')
        ->and($exception->getCode())->toBe(0);
});

it('uses defaults for a non-array body', function (): void {
    $exception = ApiException::fromResponse(new Response(500, [], 'null'));

    expect($exception->getMessage())->toBe('Unknown Api error')
        ->and($exception->getCode())->toBe(0);
});

it('uses defaults for a non-numeric error_code', function (): void {
    $body = (string) json_encode([
        'error_message' => 'Oops',
        'error_code' => 'not-a-number',
    ]);

    $exception = ApiException::fromResponse(new Response(500, [], $body));

    expect($exception->getMessage())->toBe('Oops')
        ->and($exception->getCode())->toBe(0);
});

it('captures the HTTP status code', function (): void {
    $exception = ApiException::fromResponse(new Response(500, [], '{"error_message":"Server error"}'));

    expect($exception->httpStatus)->toBe(500);
});

it('routes a 429 to a RateLimitException with the parsed Retry-After', function (): void {
    $exception = ApiException::fromResponse(
        new Response(429, ['Retry-After' => '7'], '{"error_message":"Slow down"}'),
    );

    expect($exception)->toBeInstanceOf(RateLimitException::class)
        ->and($exception->httpStatus)->toBe(429);

    if ($exception instanceof RateLimitException) {
        expect($exception->retryAfterSeconds)->toBe(7);
    }
});

it('leaves retryAfterSeconds null for a missing or non-numeric Retry-After', function (array $headers): void {
    $exception = ApiException::fromResponse(new Response(429, $headers, '{}'));

    expect($exception)->toBeInstanceOf(RateLimitException::class);

    if ($exception instanceof RateLimitException) {
        expect($exception->retryAfterSeconds)->toBeNull();
    }
})->with([
    'missing header' => [[]],
    'non-numeric header' => [['Retry-After' => 'Wed, 21 Oct 2025 07:28:00 GMT']],
]);

it('routes 401 and 403 to an AuthenticationException', function (int $status): void {
    $exception = ApiException::fromResponse(new Response($status, [], '{"error_message":"Denied"}'));

    expect($exception)->toBeInstanceOf(AuthenticationException::class)
        ->and($exception->httpStatus)->toBe($status);
})->with([[401], [403]]);

it('returns a plain ApiException for other 4xx/5xx statuses', function (): void {
    $exception = ApiException::fromResponse(new Response(404, [], '{"error_message":"Not found"}'));

    expect($exception::class)->toBe(ApiException::class);
    expect($exception->httpStatus)->toBe(404);
});
