<?php

declare(strict_types=1);

use Http\Discovery\Psr18ClientDiscovery;
use Http\Discovery\Strategy\MockClientStrategy;
use Http\Mock\Client as MockClient;
use Psr\Http\Message\RequestInterface;
use Woduda\CiviCRM\Client;
use Woduda\CiviCRM\CiviCrmClient;
use Woduda\CiviCRM\Config;
use Woduda\CiviCRM\Contract\TransportInterface;
use Woduda\CiviCRM\Result\ApiResponse;

/*
 * Allow php-http/discovery to resolve the mock client when no PSR-18
 * implementation is injected, so the auto-discovery branch is testable
 * without bundling a real HTTP client.
 */
Psr18ClientDiscovery::prependStrategy(MockClientStrategy::class);

uses()->in('Unit', 'Integration');

/**
 * In-memory spy transport for unit-testing entity API classes without HTTP.
 */
final class SpyTransport implements TransportInterface
{
    /** @var list<array{entity: string, action: string, params: array<string, mixed>}> */
    public array $calls = [];

    private ?ApiResponse $nextResponse = null;

    /**
     * Enqueues a response to be returned by the next {@see send()} call.
     */
    public function queue(ApiResponse $response): void
    {
        $this->nextResponse = $response;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function send(string $entity, string $action, array $params = []): ApiResponse
    {
        $this->calls[] = ['entity' => $entity, 'action' => $action, 'params' => $params];
        $response = $this->nextResponse ?? new ApiResponse(4, 0, []);
        $this->nextResponse = null;

        return $response;
    }
}

/**
 * Builds a CiviCrmClient wired to a fresh SpyTransport.
 *
 * @return array{0: CiviCrmClient, 1: SpyTransport}
 */
function civicrmNewClient(): array
{
    $spy = new SpyTransport();

    return [new CiviCrmClient($spy), $spy];
}

/**
 * Builds a Client wired to a fresh mock PSR-18 client.
 *
 * @return array{0: Client, 1: MockClient}
 */
function civicrmClient(): array
{
    $config = new Config('https://crm.example.org/civicrm/ajax/api4/', 'secret-key');
    $mock = new MockClient();

    return [new Client($config, $mock), $mock];
}

/**
 * Returns the URI of the last request captured by the mock client.
 */
function lastRequestUri(MockClient $mock): string
{
    $sent = $mock->getLastRequest();

    if (! $sent instanceof RequestInterface) {
        throw new RuntimeException('No request was captured by the mock client.');
    }

    return (string) $sent->getUri();
}
