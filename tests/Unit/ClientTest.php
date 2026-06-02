<?php

declare(strict_types=1);

use Nyholm\Psr7\Response;
use Woduda\CiviCRM\Client;
use Woduda\CiviCRM\Config;
use Woduda\CiviCRM\Exception\ApiException;
use Woduda\CiviCRM\Result\ApiResponse;

it('can be constructed via auto-discovery', function (): void {
    $client = new Client(new Config('https://crm.example.org/civicrm/ajax/api4/', 'k'));

    expect($client)->toBeInstanceOf(Client::class);
});

it('builds a POST request to the absolute endpoint url', function (): void {
    [$client] = civicrmClient();

    $request = $client->getRequest('Contact/get', ['select' => ['id']]);

    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())
        ->toBe('https://crm.example.org/civicrm/ajax/api4/Contact/get');
});

it('sends the required default and auth headers', function (): void {
    [$client] = civicrmClient();

    $request = $client->getRequest('Contact/get');

    expect($request->getHeaderLine('X-Requested-With'))->toBe('XMLHttpRequest')
        ->and($request->getHeaderLine('Content-Type'))->toBe('application/x-www-form-urlencoded')
        ->and($request->getHeaderLine('Authorization'))->toBe('Bearer secret-key');
});

it('form-encodes params as a JSON params field', function (): void {
    [$client] = civicrmClient();

    $params = ['where' => [['first_name', '=', 'Jane']]];
    $request = $client->getRequest('Contact/get', $params);

    $expected = 'params=' . urlencode((string) json_encode($params));

    expect((string) $request->getBody())->toBe($expected);
});

it('wraps a successful response in an ApiResponse', function (): void {
    [$client, $mock] = civicrmClient();
    $mock->addResponse(new Response(200, [], (string) json_encode([
        'version' => 4,
        'count' => 1,
        'values' => [['id' => 7]],
    ])));

    $response = $client->sendRequest('Contact/get');

    expect($response)->toBeInstanceOf(ApiResponse::class)
        ->and($response->count)->toBe(1)
        ->and($response->values)->toBe([['id' => 7]]);
});

it('actually dispatches the built request to the http client', function (): void {
    [$client, $mock] = civicrmClient();
    $mock->addResponse(new Response(200, [], '{"count":0,"values":[]}'));

    $client->sendRequest('Contact/get', ['limit' => 5]);

    expect(lastRequestUri($mock))->toEndWith('Contact/get');
});

it('throws ApiException on a 400 response (boundary)', function (): void {
    [$client, $mock] = civicrmClient();
    $mock->addResponse(new Response(400, [], (string) json_encode([
        'error_message' => 'Bad request',
        'error_code' => 400,
    ])));

    expect(fn(): ApiResponse => $client->sendRequest('Contact/get'))
        ->toThrow(ApiException::class, 'Bad request');
});

it('throws ApiException on a 500 response', function (): void {
    [$client, $mock] = civicrmClient();
    $mock->addResponse(new Response(500, [], '{"error_message":"Server error"}'));

    expect(fn(): ApiResponse => $client->sendRequest('Contact/get'))
        ->toThrow(ApiException::class, 'Server error');
});

it('does not throw on a 399 response (boundary)', function (): void {
    [$client, $mock] = civicrmClient();
    $mock->addResponse(new Response(399, [], '{"count":0,"values":[]}'));

    expect($client->sendRequest('Contact/get'))->toBeInstanceOf(ApiResponse::class);
});
