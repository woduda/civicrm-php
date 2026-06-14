<?php

declare(strict_types=1);

use Psr\Http\Client\ClientExceptionInterface;
use Woduda\CiviCRM\Exception\CivicrmException;
use Woduda\CiviCRM\Exception\TransportException;

it('wraps a throwable, preserving message and previous', function (): void {
    $previous = new RuntimeException('connection refused');

    $exception = TransportException::fromThrowable($previous);

    expect($exception)->toBeInstanceOf(CivicrmException::class)
        ->and($exception->getMessage())->toBe('connection refused')
        ->and($exception->getCode())->toBe(0)
        ->and($exception->getPrevious())->toBe($previous);
});

it('preserves a PSR-18 client exception as previous', function (): void {
    $clientException = new class ('network down') extends RuntimeException implements ClientExceptionInterface {};

    $exception = TransportException::fromThrowable($clientException);

    expect($exception->getPrevious())->toBe($clientException);
});
