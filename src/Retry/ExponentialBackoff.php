<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Retry;

use Throwable;
use Woduda\CiviCRM\Exception\ApiErrorException;
use Woduda\CiviCRM\Exception\AuthenticationException;
use Woduda\CiviCRM\Exception\RateLimitException;
use Woduda\CiviCRM\Exception\TransportException;
use Woduda\CiviCRM\Exception\ValidationException;

/**
 * Exponential back-off with optional full jitter.
 *
 * Retries transient failures only: transport-level errors, rate limiting
 * (honoring `Retry-After`), and server-side 5xx responses. Client errors —
 * validation and authentication failures — are never retried.
 */
final readonly class ExponentialBackoff implements RetryStrategy
{
    /**
     * @param int   $maxAttempts Total attempts allowed, including the first try
     * @param int   $baseDelayMs Delay before the second attempt, in milliseconds
     * @param float $multiplier  Growth factor applied per attempt
     * @param int   $maxDelayMs  Upper bound on any single delay, in milliseconds
     * @param bool  $jitter      Whether to apply full jitter to each delay
     */
    public function __construct(
        private int $maxAttempts = 3,
        private int $baseDelayMs = 200,
        private float $multiplier = 2.0,
        private int $maxDelayMs = 5000,
        private bool $jitter = true,
    ) {}

    public function shouldRetry(int $attempt, Throwable $e): bool
    {
        if ($attempt >= $this->maxAttempts) {
            return false;
        }

        return match (true) {
            $e instanceof ValidationException, $e instanceof AuthenticationException => false,
            $e instanceof RateLimitException, $e instanceof TransportException => true,
            $e instanceof ApiErrorException => $e->httpStatus !== null && $e->httpStatus >= 500 && $e->httpStatus < 600,
            default => false,
        };
    }

    public function delayMs(int $attempt, ?Throwable $e = null): int
    {
        if ($e instanceof RateLimitException && $e->retryAfterSeconds !== null) {
            return min($e->retryAfterSeconds * 1000, $this->maxDelayMs);
        }

        $raw = (int) round($this->baseDelayMs * ($this->multiplier ** ($attempt - 1)));
        $capped = min($raw, $this->maxDelayMs);

        if ($this->jitter) {
            return random_int(0, $capped);
        }

        return $capped;
    }
}
