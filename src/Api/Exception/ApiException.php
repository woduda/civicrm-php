<?php

namespace Woduda\CiviCRM\Api\Exception;

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

        throw new static(
            $json['error_message'] ?? 'Unknown Api error',
            $json['error_code'] ?? 0
        );
    }
}
