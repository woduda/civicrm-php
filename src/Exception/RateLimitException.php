<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Exception;

/**
 * Thrown when CiviCRM returns HTTP 429 (Too Many Requests).
 *
 * Carries the `Retry-After` hint (in seconds) when the server provided one,
 * so a retry strategy can honor the server-requested back-off window.
 */
final class RateLimitException extends ApiErrorException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?int $httpStatus = null,
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($message, $code, $httpStatus);
    }
}
