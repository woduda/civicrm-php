<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Exception;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * ApiException thrown in case of HTTP 4xx/5xx response
 * from CiviCRM API
 */
class ApiException extends RuntimeException
{
    /**
     * Creates Exception from Response received from API
     *
     * @param ResponseInterface $response
     * @return ApiException
     */
    public static function fromResponse(ResponseInterface $response): ApiException
    {
        $json = json_decode($response->getBody()->getContents(), true);

        return new self(
            is_array($json) ? ($json['error_message'] ?? 'Unknown Api error') : 'Unknown Api error',
            is_array($json) ? (int) ($json['error_code'] ?? 0) : 0,
        );
    }
}
