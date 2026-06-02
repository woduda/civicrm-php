<?php

declare(strict_types=1);

use Http\Discovery\Psr18ClientDiscovery;
use Http\Discovery\Strategy\MockClientStrategy;
use Http\Mock\Client as MockClient;
use Psr\Http\Message\RequestInterface;
use Woduda\CiviCRM\Client;
use Woduda\CiviCRM\Config;

/*
 * Allow php-http/discovery to resolve the mock client when no PSR-18
 * implementation is injected, so the auto-discovery branch is testable
 * without bundling a real HTTP client.
 */
Psr18ClientDiscovery::prependStrategy(MockClientStrategy::class);

uses()->in('Unit', 'Integration');

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
