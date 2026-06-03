<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Exception;

use InvalidArgumentException;

/**
 * Thrown when a query/action builder receives an invalid argument.
 */
final class ValidationException extends InvalidArgumentException implements CivicrmException
{
    /**
     * Builds an exception for an unsupported order-by direction.
     *
     * Example:
     * ```php
     * throw ValidationException::invalidOrderDirection('UPWARDS');
     * ```
     */
    public static function invalidOrderDirection(string $direction): self
    {
        return new self(sprintf(
            'Invalid order direction "%s"; expected "ASC" or "DESC".',
            $direction,
        ));
    }

    /**
     * Builds an exception for a custom field that does not exist.
     *
     * Example:
     * ```php
     * throw ValidationException::unknownCustomField('Wolontariat', 'volunteer_status');
     * ```
     */
    public static function unknownCustomField(string $groupName, string $fieldName): self
    {
        return new self(sprintf(
            'Custom field "%s.%s" does not exist.',
            $groupName,
            $fieldName,
        ));
    }
}
