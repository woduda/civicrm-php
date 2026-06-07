<?php

declare(strict_types=1);

use Nyholm\Psr7\Response;
use Woduda\CiviCRM\Config;
use Woduda\CiviCRM\Http\Transport;
use Woduda\CiviCRM\Result\ApiResponse;

it('send delegates to Client with entity/action URI', function (): void {
    [$client, $mock] = civicrmClient();
    $mock->addResponse(new Response(200, [], (string) json_encode([
        'values' => [['id' => 1]],
        'count' => 1,
    ])));

    $response = (new Transport($client))->send('Email', 'get', ['limit' => 1]);

    expect($response)->toBeInstanceOf(ApiResponse::class)
        ->and($response->count)->toBe(1)
        ->and(lastRequestUri($mock))->toBe('https://crm.example.org/civicrm/ajax/api4/Email/get');
});

it('createDefault builds a working transport', function (): void {
    $transport = Transport::createDefault(new Config('https://crm.example.org/civicrm/ajax/api4/', 'k'));

    expect($transport)->toBeInstanceOf(Transport::class);
});
