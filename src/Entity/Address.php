<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Entity;

/**
 * Typed representation of a CiviCRM Address record.
 */
final readonly class Address implements FromArrayInterface
{
    /**
     * @param array<string, mixed> $rawData Full original APIv4 row
     */
    public function __construct(
        public int $id,
        public int $contactId,
        public string $locationType,
        public bool $isPrimary,
        public bool $isBilling,
        public string $streetAddress,
        public ?string $supplementalAddress1,
        public string $city,
        public string $postalCode,
        public ?int $countryId,
        public ?int $stateProvinceId,
        public array $rawData,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            id: self::toInt($row['id'] ?? null),
            contactId: self::toInt($row['contact_id'] ?? null),
            locationType: isset($row['location_type_id.name']) && is_string($row['location_type_id.name'])
                ? $row['location_type_id.name']
                : '',
            isPrimary: self::toBool($row['is_primary'] ?? false),
            isBilling: self::toBool($row['is_billing'] ?? false),
            streetAddress: isset($row['street_address']) && is_string($row['street_address'])
                ? $row['street_address']
                : '',
            supplementalAddress1: isset($row['supplemental_address_1']) && is_string($row['supplemental_address_1'])
                ? $row['supplemental_address_1']
                : null,
            city: isset($row['city']) && is_string($row['city']) ? $row['city'] : '',
            postalCode: isset($row['postal_code']) && is_string($row['postal_code']) ? $row['postal_code'] : '',
            countryId: isset($row['country_id']) && is_int($row['country_id']) ? $row['country_id'] : null,
            stateProvinceId: isset($row['state_province_id']) && is_int($row['state_province_id'])
                ? $row['state_province_id']
                : null,
            rawData: $row,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'contact_id' => $this->contactId,
            'street_address' => $this->streetAddress,
            'city' => $this->city,
            'postal_code' => $this->postalCode,
            'is_primary' => $this->isPrimary,
            'is_billing' => $this->isBilling,
        ];

        if ($this->locationType !== '') {
            $data['location_type_id.name'] = $this->locationType;
        }

        if ($this->supplementalAddress1 !== null) {
            $data['supplemental_address_1'] = $this->supplementalAddress1;
        }

        if ($this->countryId !== null) {
            $data['country_id'] = $this->countryId;
        }

        if ($this->stateProvinceId !== null) {
            $data['state_province_id'] = $this->stateProvinceId;
        }

        return $data;
    }

    private static function toInt(mixed $value): int
    {
        return is_int($value) ? $value : 0;
    }

    private static function toBool(mixed $value): bool
    {
        return in_array($value, [true, 1, '1'], true);
    }
}
