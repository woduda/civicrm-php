<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Http;

use Psr\Http\Client\ClientExceptionInterface;
use Woduda\CiviCRM\Client;
use Woduda\CiviCRM\Config;
use Woduda\CiviCRM\Contract\TransportInterface;
use Woduda\CiviCRM\Exception\ApiException;
use Woduda\CiviCRM\Result\ApiResponse;

/**
 * Default PSR-18 transport — delegates to {@see Client}.
 */
final readonly class Transport implements TransportInterface
{
    public function __construct(private Client $httpClient) {}

    /**
     * Creates a Transport wired to a new auto-discovered PSR-18 HTTP client.
     */
    public static function createDefault(Config $config): self
    {
        return new self(new Client($config));
    }

    /**
     * @param  array<string, mixed> $params
     * @throws ApiException             On HTTP 4xx/5xx responses
     * @throws ClientExceptionInterface On transport-level errors
     */
    public function send(string $entity, string $action, array $params = []): ApiResponse
    {
        return $this->httpClient->sendRequest($entity . '/' . $action, $params);
    }
}
