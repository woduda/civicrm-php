<?php

declare(strict_types=1);

use Nyholm\Psr7\Response;
use Woduda\CiviCRM\Exception\ApiException;

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
