<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Retry;

use Throwable;

/**
 * Policy deciding whether and how a failed transport call should be retried.
 */
interface RetryStrategy
{
    /**
     * Returns whether the given attempt that failed with $e should be retried.
     *
     * @param int $attempt 1-based number of the attempt that just failed
     */
    public function shouldRetry(int $attempt, Throwable $e): bool;

    /**
     * Returns the delay, in milliseconds, to wait before the next attempt.
     *
     * The failing throwable is passed so strategies can honor a server-provided
     * back-off hint (e.g. a `Retry-After` header surfaced on the exception).
     *
     * @param int $attempt 1-based number of the attempt that just failed
     */
    public function delayMs(int $attempt, ?Throwable $e = null): int;
}
