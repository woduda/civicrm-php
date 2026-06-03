<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Contract;

use Psr\Http\Client\ClientExceptionInterface;
use Woduda\CiviCRM\Exception\ApiException;
use Woduda\CiviCRM\Result\ApiResponse;

interface TransportInterface
{
    /**
     * Sends a CiviCRM APIv4 request and returns the parsed response.
     *
     * @param  array<string, mixed> $params
     * @throws ApiException             On HTTP 4xx/5xx responses
     * @throws ClientExceptionInterface On transport-level errors
     */
    public function send(string $entity, string $action, array $params = []): ApiResponse;
}
