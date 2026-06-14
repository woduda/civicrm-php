<?php

declare(strict_types=1);

use Http\Discovery\Psr18ClientDiscovery;
use Http\Discovery\Strategy\MockClientStrategy;
use Http\Mock\Client as MockClient;
use Psr\Http\Message\RequestInterface;
use Psr\Log\AbstractLogger;
use Stringable;
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

    /** @var list<ApiResponse> */
    private array $responseQueue = [];

    /**
     * Enqueues a response to be returned by the next {@see send()} call.
     * Multiple calls enqueue in FIFO order; unqueued calls return an empty response.
     */
    public function queue(ApiResponse $response): void
    {
        $this->responseQueue[] = $response;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function send(string $entity, string $action, array $params = []): ApiResponse
    {
        $this->calls[] = ['entity' => $entity, 'action' => $action, 'params' => $params];

        if ($this->responseQueue !== []) {
            return array_shift($this->responseQueue);
        }

        return new ApiResponse(4, 0, []);
    }
}

/**
 * Records the milliseconds it is asked to sleep without ever sleeping.
 *
 * Pass to {@see \Woduda\CiviCRM\Http\Transport} as `$spy(...)` (a Closure)
 * and inspect {@see self::$calls} afterwards.
 */
final class SpySleeper
{
    /** @var list<int> */
    public array $calls = [];

    public function __invoke(int $ms): void
    {
        $this->calls[] = $ms;
    }
}

/**
 * In-memory PSR-3 logger capturing every record for assertions.
 */
final class SpyLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string, context: array<mixed>}> */
    public array $records = [];

    /**
     * @param array<mixed> $context
     */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    /**
     * Returns every record at the given PSR-3 level.
     *
     * @return list<array{level: mixed, message: string, context: array<mixed>}>
     */
    public function recordsAt(string $level): array
    {
        return array_values(array_filter($this->records, static fn(array $record): bool => $record['level'] === $level));
    }

    /**
     * Serializes all captured records so tests can assert on their full content.
     */
    public function dump(): string
    {
        return (string) json_encode($this->records);
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
 * Loads the first values row from a JSON fixture under tests/Fixtures/.
 *
 * @return array<string, mixed>
 */
function fixtureFirstRow(string $filename): array
{
    $contents = file_get_contents(__DIR__ . '/Fixtures/' . $filename);

    if ($contents === false) {
        return [];
    }

    $decoded = json_decode($contents, true);

    if (! is_array($decoded)) {
        return [];
    }

    $values = $decoded['values'] ?? null;

    if (! is_array($values)) {
        return [];
    }

    $first = $values[0] ?? null;

    if (! is_array($first)) {
        return [];
    }

    $row = [];

    foreach ($first as $key => $value) {
        if (is_string($key)) {
            $row[$key] = $value;
        }
    }

    return $row;
}

/**
 * Loads count and values from a JSON fixture under tests/Fixtures/.
 *
 * @return array{count: int<0, max>, values: list<array<string, mixed>>}
 */
function fixtureApiPayload(string $filename): array
{
    $contents = file_get_contents(__DIR__ . '/Fixtures/' . $filename);

    if ($contents === false) {
        return ['count' => 0, 'values' => []];
    }

    $decoded = json_decode($contents, true);

    if (! is_array($decoded)) {
        return ['count' => 0, 'values' => []];
    }

    $count = $decoded['count'] ?? null;
    $rawValues = $decoded['values'] ?? null;
    $values = [];

    if (is_array($rawValues)) {
        foreach ($rawValues as $row) {
            if (! is_array($row)) {
                continue;
            }

            $normalized = [];

            foreach ($row as $key => $value) {
                if (is_string($key)) {
                    $normalized[$key] = $value;
                }
            }

            $values[] = $normalized;
        }
    }

    return [
        'count' => is_int($count) ? max(0, $count) : 0,
        'values' => $values,
    ];
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
