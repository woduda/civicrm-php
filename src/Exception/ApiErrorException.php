<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Exception;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Thrown when CiviCRM returns an HTTP 4xx/5xx error response.
 *
 * Acts as the base type for status-specific subclasses
 * ({@see RateLimitException}, {@see AuthenticationException}); the concrete
 * type is selected by {@see self::fromResponse()} based on the HTTP status.
 */
class ApiErrorException extends RuntimeException implements CivicrmException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        public readonly ?int $httpStatus = null,
    ) {
        parent::__construct($message, $code);
    }

    /**
     * Builds an exception from an error response returned by the API.
     *
     * Routes to the most specific subclass based on the HTTP status code:
     * 429 -> {@see RateLimitException}, 401/403 -> {@see AuthenticationException},
     * everything else -> {@see self}.
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

        $status = $response->getStatusCode();

        return match (true) {
            $status === 429 => new RateLimitException(
                $message,
                $code,
                $status,
                self::parseRetryAfter($response),
            ),
            $status === 401, $status === 403 => new AuthenticationException($message, $code, $status),
            default => new self($message, $code, $status),
        };
    }

    /**
     * Parses the `Retry-After` header as an integer number of seconds.
     *
     * Returns null when the header is absent or not a non-negative integer.
     */
    private static function parseRetryAfter(ResponseInterface $response): ?int
    {
        $value = $response->getHeaderLine('Retry-After');

        // ctype_digit('') is false, so this also rejects a missing header.
        if (! ctype_digit($value)) {
            return null;
        }

        return (int) $value;
    }
}
