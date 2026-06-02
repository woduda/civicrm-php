<?php

declare(strict_types=1);

namespace Woduda\CiviCRM;

/**
 * Immutable client configuration.
 */
final readonly class Config
{
    /**
     * @param string                $baseUrl Base URL of the CiviCRM APIv4 endpoint,
     *                                       e.g. https://example.org/civicrm/ajax/api4/
     * @param string                $apiKey  Secret bearer token / API key issued by CiviCRM
     * @param array<string, string> $headers Additional headers sent with every request
     */
    public function __construct(
        private string $baseUrl,
        private string $apiKey,
        private array $headers = [],
    ) {}

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}
