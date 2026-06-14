<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Exception;

use RuntimeException;
use Throwable;

/**
 * Wraps a PSR-18 transport-level failure (network error, DNS failure, timeout)
 * raised before any HTTP response was received.
 *
 * The original {@see \Psr\Http\Client\ClientExceptionInterface} is preserved as
 * the previous exception.
 */
final class TransportException extends RuntimeException implements CivicrmException
{
    public static function fromThrowable(Throwable $previous): self
    {
        return new self($previous->getMessage(), 0, $previous);
    }
}
