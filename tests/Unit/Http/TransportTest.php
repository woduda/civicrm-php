<?php

declare(strict_types=1);

use Http\Client\Exception\TransferException;
use Nyholm\Psr7\Response;
use Woduda\CiviCRM\Config;
use Woduda\CiviCRM\Exception\ApiException;
use Woduda\CiviCRM\Exception\TransportException;
use Woduda\CiviCRM\Http\Transport;
use Woduda\CiviCRM\Result\ApiResponse;
use Woduda\CiviCRM\Retry\ExponentialBackoff;

function okResponse(): Response
{
    return new Response(200, [], (string) json_encode(['values' => [['id' => 1]], 'count' => 1]));
}

it('send delegates to Client with entity/action URI', function (): void {
    [$client, $mock] = civicrmClient();
    $mock->addResponse(okResponse());

    $response = (new Transport($client))->send('Email', 'get', ['limit' => 1]);

    expect($response)->toBeInstanceOf(ApiResponse::class)
        ->and($response->count)->toBe(1)
        ->and(lastRequestUri($mock))->toBe('https://crm.example.org/civicrm/ajax/api4/Email/get');
});

it('createDefault builds a working transport', function (): void {
    $transport = Transport::createDefault(new Config('https://crm.example.org/civicrm/ajax/api4/', 'k'));

    expect($transport)->toBeInstanceOf(Transport::class);
});

it('retries 503 responses and succeeds on the third attempt', function (): void {
    [$client, $mock] = civicrmClient();
    $mock->addResponse(new Response(503, [], '{"error_message":"unavailable"}'));
    $mock->addResponse(new Response(503, [], '{"error_message":"unavailable"}'));
    $mock->addResponse(okResponse());

    $sleeper = new SpySleeper();
    $transport = new Transport($client, new ExponentialBackoff(maxAttempts: 3, jitter: false), null, $sleeper(...));

    $response = $transport->send('Contact', 'get');

    expect($response->count)->toBe(1)
        ->and($sleeper->calls)->toBe([200, 400]);
});

it('respects the Retry-After header on a 429', function (): void {
    [$client, $mock] = civicrmClient();
    $mock->addResponse(new Response(429, ['Retry-After' => '2'], '{"error_message":"slow down"}'));
    $mock->addResponse(okResponse());

    $sleeper = new SpySleeper();
    $transport = new Transport($client, new ExponentialBackoff(maxAttempts: 2, jitter: false), null, $sleeper(...));

    $transport->send('Contact', 'get');

    expect($sleeper->calls)->toBe([2000]);
});

it('retries a transport-level error and then succeeds', function (): void {
    [$client, $mock] = civicrmClient();
    $mock->addException(new TransferException('network down'));
    $mock->addResponse(okResponse());

    $sleeper = new SpySleeper();
    $transport = new Transport($client, new ExponentialBackoff(maxAttempts: 2, jitter: false), null, $sleeper(...));

    $response = $transport->send('Contact', 'get');

    expect($response->count)->toBe(1)
        ->and($sleeper->calls)->toHaveCount(1);
});

it('wraps an exhausted transport-level error in a TransportException', function (): void {
    [$client, $mock] = civicrmClient();
    $mock->addException(new TransferException('down'));
    $mock->addException(new TransferException('down'));

    $sleeper = new SpySleeper();
    $transport = new Transport($client, new ExponentialBackoff(maxAttempts: 2, jitter: false), null, $sleeper(...));

    expect(fn(): ApiResponse => $transport->send('Contact', 'get'))
        ->toThrow(TransportException::class, 'down');
});

it('does not retry a non-5xx ApiException', function (): void {
    [$client, $mock] = civicrmClient();
    $mock->addResponse(new Response(400, [], '{"error_message":"bad request"}'));

    $sleeper = new SpySleeper();
    $transport = new Transport($client, new ExponentialBackoff(maxAttempts: 3, jitter: false), null, $sleeper(...));

    expect(fn(): ApiResponse => $transport->send('Contact', 'get'))
        ->toThrow(ApiException::class, 'bad request')
        ->and($sleeper->calls)->toBe([]);
});

it('does not retry under the default NoRetry strategy', function (): void {
    [$client, $mock] = civicrmClient();
    $mock->addResponse(new Response(503, [], '{"error_message":"unavailable"}'));

    expect(fn(): ApiResponse => (new Transport($client))->send('Contact', 'get'))
        ->toThrow(ApiException::class, 'unavailable');
});
