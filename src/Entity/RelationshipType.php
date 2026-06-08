<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Entity;

/**
 * Typed representation of a CiviCRM RelationshipType record.
 *
 * A relationship type is inherently **bidirectional and asymmetric**: the same
 * type carries two different machine names and two different human labels
 * depending on the direction it is read in.
 *
 * For the classic "Reports to" / "Manages" type:
 * - `nameAToB` / `labelAToB` describe the A→B direction — contact A *reports to*
 *   contact B (label "Reports to").
 * - `nameBToA` / `labelBToA` describe the reverse B→A direction — contact B
 *   *manages* contact A (label "Manages").
 *
 * `contactTypeA` / `contactTypeB` optionally constrain which contact types may
 * occupy each side (`'Individual'`, `'Organization'`, `'Household'`); `null`
 * means any contact type is allowed on that side.
 */
final readonly class RelationshipType implements FromArrayInterface
{
    /**
     * @param array<string, mixed> $rawData Full original APIv4 row
     */
    public function __construct(
        public int $id,
        public string $nameAToB,
        public string $nameBToA,
        public string $labelAToB,
        public string $labelBToA,
        public ?string $contactTypeA,
        public ?string $contactTypeB,
        public array $rawData,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            id: isset($row['id']) && is_int($row['id']) ? $row['id'] : 0,
            nameAToB: self::toString($row['name_a_b'] ?? null),
            nameBToA: self::toString($row['name_b_a'] ?? null),
            labelAToB: self::toString($row['label_a_b'] ?? null),
            labelBToA: self::toString($row['label_b_a'] ?? null),
            contactTypeA: self::toNullableString($row['contact_type_a'] ?? null),
            contactTypeB: self::toNullableString($row['contact_type_b'] ?? null),
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
            'name_a_b' => $this->nameAToB,
            'name_b_a' => $this->nameBToA,
            'label_a_b' => $this->labelAToB,
            'label_b_a' => $this->labelBToA,
        ];

        if ($this->contactTypeA !== null) {
            $data['contact_type_a'] = $this->contactTypeA;
        }

        if ($this->contactTypeB !== null) {
            $data['contact_type_b'] = $this->contactTypeB;
        }

        return $data;
    }

    private static function toString(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function toNullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
