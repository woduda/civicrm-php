<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Entity;

/**
 * Input DTO for address values with human-readable country and state names.
 *
 * Country and state are resolved to IDs by {@see \Woduda\CiviCRM\Api\AddressApi}.
 */
final readonly class AddressData
{
    public function __construct(
        public string $streetAddress,
        public ?string $supplementalAddress1,
        public string $city,
        public string $postalCode,
        public ?string $country,
        public ?string $stateProvince,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        $streetAddress = self::stringField($row, 'street_address', 'streetAddress') ?? '';
        $supplemental = self::stringField($row, 'supplemental_address_1', 'supplementalAddress1');
        $city = self::stringField($row, 'city', 'city') ?? '';
        $postalCode = self::stringField($row, 'postal_code', 'postalCode') ?? '';
        $country = self::stringField($row, 'country', 'country');
        $stateProvince = self::stringField($row, 'state_province', 'stateProvince');

        return new self(
            streetAddress: $streetAddress,
            supplementalAddress1: $supplemental,
            city: $city,
            postalCode: $postalCode,
            country: $country,
            stateProvince: $stateProvince,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function stringField(array $row, string $snakeKey, string $camelKey): ?string
    {
        if (isset($row[$snakeKey]) && is_string($row[$snakeKey])) {
            return $row[$snakeKey];
        }

        if (isset($row[$camelKey]) && is_string($row[$camelKey])) {
            return $row[$camelKey];
        }

        return null;
    }
}
