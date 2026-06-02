<?php

declare(strict_types=1);

use Nyholm\Psr7\Response;
use Woduda\CiviCRM\Result\ApiResponse;

it('is constructed from explicit values', function (): void {
    $response = new ApiResponse(version: 4, count: 2, values: [['id' => 1]]);

    expect($response->version)->toBe(4)
        ->and($response->count)->toBe(2)
        ->and($response->values)->toBe([['id' => 1]]);
});

it('parses a full JSON body', function (): void {
    $body = (string) json_encode([
        'version' => 4,
        'count' => 2,
        'values' => [['id' => 1], ['id' => 2]],
    ]);

    $response = ApiResponse::fromResponse(new Response(200, [], $body));

    expect($response->version)->toBe(4)
        ->and($response->count)->toBe(2)
        ->and($response->values)->toBe([['id' => 1], ['id' => 2]]);
});

it('applies defaults for missing keys', function (): void {
    $response = ApiResponse::fromResponse(new Response(200, [], '{}'));

    expect($response->version)->toBe(4)
        ->and($response->count)->toBe(0)
        ->and($response->values)->toBe([]);
});

it('falls back to defaults for a non-array body', function (): void {
    $response = ApiResponse::fromResponse(new Response(200, [], '"plain string"'));

    expect($response->version)->toBe(4)
        ->and($response->count)->toBe(0)
        ->and($response->values)->toBe([]);
});

it('ignores values of the wrong type', function (): void {
    $body = (string) json_encode([
        'version' => 'not-int',
        'count' => 'not-int',
        'values' => 'not-array',
    ]);

    $response = ApiResponse::fromResponse(new Response(200, [], $body));

    expect($response->version)->toBe(4)
        ->and($response->count)->toBe(0)
        ->and($response->values)->toBe([]);
});
