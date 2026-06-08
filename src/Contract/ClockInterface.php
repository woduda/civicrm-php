<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Contract;

use DateTimeImmutable;

/**
 * Provides the current wall-clock time.
 *
 * Inject this into any class that needs "now" so that tests can substitute a
 * fixed instant without relying on real system time.
 */
interface ClockInterface
{
    public function now(): DateTimeImmutable;
}
