<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Contract;

use Woduda\CiviCRM\Exception\ApiErrorException;
use Woduda\CiviCRM\Exception\TransportException;
use Woduda\CiviCRM\Result\ApiResponse;

interface TransportInterface
{
    /**
     * Sends a CiviCRM APIv4 request and returns the parsed response.
     *
     * @param  array<string, mixed> $params
     * @throws ApiErrorException  On HTTP 4xx/5xx responses
     * @throws TransportException On transport-level (network) errors
     */
    public function send(string $entity, string $action, array $params = []): ApiResponse;
}
