<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Entity;

use DateTimeImmutable;

/**
 * Aggregated donation statistics for a single contact and currency.
 *
 * Produced by {@see \Woduda\CiviCRM\Api\ContributionApi::totalsForContact()}.
 * All monetary amounts are in the currency specified at query time.
 *
 * When the contact has no contributions for a given window, the corresponding
 * total is `0.0` and the count is `0`. Date properties are `null` when
 * there are no lifetime contributions at all.
 */
final readonly class ContributionTotals
{
    public function __construct(
        public float $lifetimeTotal,
        public int $lifetimeCount,
        public float $last12MonthsTotal,
        public int $last12MonthsCount,
        public ?DateTimeImmutable $firstContributionAt,
        public ?DateTimeImmutable $lastContributionAt,
        public string $currency,
    ) {}
}
