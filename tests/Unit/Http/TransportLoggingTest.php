<?php

declare(strict_types=1);

use Http\Client\Exception\TransferException;
use Nyholm\Psr7\Response;
use Woduda\CiviCRM\Exception\ApiErrorException;
use Woduda\CiviCRM\Exception\TransportException;
use Woduda\CiviCRM\Http\Transport;
use Woduda\CiviCRM\Retry\ExponentialBackoff;

it('logs a redacted debug entry on every request', function (): void {
    [$client, $mock] = civicrmClient();
    $mock->addResponse(new Response(200, [], '{"values":[],"count":0}'));

    $logger = new SpyLogger();
    $transport = new Transport($client, logger: $logger);

    $transport->send('Contact', 'create', ['values' => ['first_name' => 'Jane', 'national_id' => '12345678901']]);

    $debug = $logger->recordsAt('debug');

    expect($debug)->toHaveCount(1)
        ->and($debug[0]['context'])->toBe([
            'entity' => 'Contact',
            'action' => 'create',
            'attempt' => 1,
            'params' => ['values' => '[REDACTED]'],
        ]);
});

it('never leaks the API key or sensitive values into any log record', function (): void {
    [$client, $mock] = civicrmClient();
    $mock->addResponse(new Response(503, [], '{"error_message":"unavailable"}'));
    $mock->addResponse(new Response(200, [], '{"values":[],"count":0}'));

    $logger = new SpyLogger();
    $sleeper = new SpySleeper();
    $transport = new Transport(
        $client,
        new ExponentialBackoff(maxAttempts: 2, jitter: false),
        $logger,
        $sleeper(...),
    );

    $transport->send('Contact', 'create', ['values' => ['national_id' => '12345678901']]);

    $serialized = $logger->dump();

    // 'secret-key' is the api key wired by civicrmClient(); it must never surface.
    expect($serialized)->not->toContain('secret-key')
        ->and($serialized)->not->toContain('12345678901')
        ->and($serialized)->not->toContain('Bearer');

    $warnings = $logger->recordsAt('warning');

    expect($warnings)->toHaveCount(1)
        ->and($warnings[0]['context'])->toBe([
            'entity' => 'Contact',
            'action' => 'create',
            'attempt' => 1,
            'delay_ms' => 200,
            'exception' => ApiErrorException::class,
            'http_status' => 503,
        ]);
});

it('logs an error and rethrows when retries are exhausted', function (): void {
    [$client, $mock] = civicrmClient();
    $mock->addResponse(new Response(500, [], '{"error_message":"boom"}'));

    $logger = new SpyLogger();
    $transport = new Transport($client, logger: $logger);

    expect(fn() => $transport->send('Contact', 'get'))->toThrow(ApiErrorException::class, 'boom');

    $errors = $logger->recordsAt('error');

    expect($errors)->toHaveCount(1)
        ->and($errors[0]['context'])->toBe([
            'entity' => 'Contact',
            'action' => 'get',
            'attempt' => 1,
            'exception' => ApiErrorException::class,
            'http_status' => 500,
        ]);
});

it('logs a null http_status when retrying a transport-level failure', function (): void {
    [$client, $mock] = civicrmClient();
    $mock->addException(new TransferException('flaky network'));
    $mock->addResponse(new Response(200, [], '{"values":[],"count":0}'));

    $logger = new SpyLogger();
    $sleeper = new SpySleeper();
    $transport = new Transport(
        $client,
        new ExponentialBackoff(maxAttempts: 2, jitter: false),
        $logger,
        $sleeper(...),
    );

    $transport->send('Contact', 'get');

    $warnings = $logger->recordsAt('warning');

    expect($warnings)->toHaveCount(1)
        ->and($warnings[0]['context']['exception'])->toBe(TransportException::class)
        ->and($warnings[0]['context']['http_status'])->toBeNull();
});

it('logs a null http_status for a transport-level failure', function (): void {
    [$client, $mock] = civicrmClient();
    $mock->addException(new TransferException('network down'));

    $logger = new SpyLogger();
    $transport = new Transport($client, logger: $logger);

    expect(fn() => $transport->send('Contact', 'get'))->toThrow(TransportException::class);

    $errors = $logger->recordsAt('error');

    expect($errors)->toHaveCount(1)
        ->and($errors[0]['context'])->toBe([
            'entity' => 'Contact',
            'action' => 'get',
            'attempt' => 1,
            'exception' => TransportException::class,
            'http_status' => null,
        ]);
});
