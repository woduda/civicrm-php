<?php

declare(strict_types=1);

use Woduda\CiviCRM\Exception\TransportException;
use Woduda\CiviCRM\Retry\NoRetry;

it('never retries regardless of attempt or exception', function (): void {
    $strategy = new NoRetry();

    expect($strategy->shouldRetry(1, new TransportException('boom')))->toBeFalse()
        ->and($strategy->shouldRetry(5, new RuntimeException('boom')))->toBeFalse();
});

it('always returns a zero delay', function (): void {
    $strategy = new NoRetry();

    expect($strategy->delayMs(1))->toBe(0)
        ->and($strategy->delayMs(3, new TransportException('boom')))->toBe(0);
});
