<?php

declare(strict_types=1);

use Woduda\CiviCRM\SystemClock;

it('now() returns a DateTimeImmutable', function (): void {
    $clock = new SystemClock();

    expect($clock->now())->toBeInstanceOf(DateTimeImmutable::class);
});

it('now() returns the current time within a one-second window', function (): void {
    $before = new DateTimeImmutable();
    $now = (new SystemClock())->now();
    $after = new DateTimeImmutable();

    expect($now->getTimestamp())->toBeGreaterThanOrEqual($before->getTimestamp())
        ->and($now->getTimestamp())->toBeLessThanOrEqual($after->getTimestamp());
});
