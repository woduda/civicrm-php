<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Exception;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Thrown when CiviCRM returns an HTTP 4xx/5xx response.
 */
class ApiException extends RuntimeException
{
    /**
     * Builds an exception from an error response returned by the API.
     */
    public static function fromResponse(ResponseInterface $response): self
    {
        $json = json_decode($response->getBody()->getContents(), true);

        $message = is_array($json) && isset($json['error_message']) && is_string($json['error_message'])
            ? $json['error_message']
            : 'Unknown Api error';

        $code = is_array($json) && isset($json['error_code']) && is_numeric($json['error_code'])
            ? (int) $json['error_code']
            : 0;

        return new self($message, $code);
    }
}
