<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Retry;

use Throwable;

/**
 * Default strategy that never retries — preserves single-attempt behavior when
 * no resilience is configured.
 */
final readonly class NoRetry implements RetryStrategy
{
    public function shouldRetry(int $attempt, Throwable $e): bool
    {
        return false;
    }

    public function delayMs(int $attempt, ?Throwable $e = null): int
    {
        return 0;
    }
}
