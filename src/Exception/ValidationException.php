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

    /**
     * Builds an exception for a country that could not be resolved.
     *
     * Example:
     * ```php
     * throw ValidationException::unknownCountry('XX');
     * ```
     */
    public static function unknownCountry(string $country): self
    {
        return new self(sprintf(
            'Country "%s" does not exist.',
            $country,
        ));
    }

    /**
     * Builds an exception for a state/province that could not be resolved.
     *
     * Example:
     * ```php
     * throw ValidationException::unknownStateProvince('Unknown State');
     * ```
     */
    public static function unknownStateProvince(string $stateProvince): self
    {
        return new self(sprintf(
            'State/province "%s" does not exist.',
            $stateProvince,
        ));
    }

    /**
     * Builds an exception when an API action returns no records.
     *
     * Example:
     * ```php
     * throw ValidationException::emptyApiResult('Email', 'create');
     * ```
     */
    public static function emptyApiResult(string $entity, string $action): self
    {
        return new self(sprintf(
            '%s.%s returned no records.',
            $entity,
            $action,
        ));
    }
}
