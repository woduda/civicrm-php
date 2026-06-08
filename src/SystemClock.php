<?php

declare(strict_types=1);

namespace Woduda\CiviCRM;

use DateTimeImmutable;
use Woduda\CiviCRM\Contract\ClockInterface;

/**
 * Default clock implementation that returns the real system time.
 */
final readonly class SystemClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
