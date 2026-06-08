<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Entity;

use DateTimeImmutable;

/**
 * Typed representation of a CiviCRM Relationship record (a link between two contacts).
 *
 * A relationship is **directional**: it connects contact A (`contactIdA`) to
 * contact B (`contactIdB`) under a single {@see RelationshipType}. The same record
 * reads differently in each direction, which is why the two human labels are kept
 * separate.
 *
 * For a "Reports to" / "Manages" relationship where employee #42 reports to
 * manager #7 (`contactIdA = 42`, `contactIdB = 7`):
 * - `labelAToB` is "Reports to" — contact A *reports to* contact B.
 * - `labelBToA` is "Manages" — contact B *manages* contact A.
 *
 * `labelAToB` / `labelBToA` are only populated when the caller joins them into the
 * `get` query (e.g. via `relationship_type_id.label_a_b`); otherwise they are `null`.
 */
final readonly class Relationship implements FromArrayInterface
{
    /**
     * @param array<string, mixed> $rawData Full original APIv4 row
     */
    public function __construct(
        public int $id,
        public int $contactIdA,
        public int $contactIdB,
        public int $relationshipTypeId,
        public ?DateTimeImmutable $startDate,
        public ?DateTimeImmutable $endDate,
        public bool $isActive,
        public ?string $description,
        public ?string $labelAToB,
        public ?string $labelBToA,
        public array $rawData,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            id: self::toInt($row['id'] ?? null),
            contactIdA: self::toInt($row['contact_id_a'] ?? null),
            contactIdB: self::toInt($row['contact_id_b'] ?? null),
            relationshipTypeId: self::toInt($row['relationship_type_id'] ?? null),
            startDate: self::toDate($row['start_date'] ?? null),
            endDate: self::toDate($row['end_date'] ?? null),
            isActive: self::toBool($row['is_active'] ?? false),
            description: isset($row['description']) && is_string($row['description'])
                ? $row['description']
                : null,
            labelAToB: isset($row['relationship_type_id.label_a_b'])
                && is_string($row['relationship_type_id.label_a_b'])
                ? $row['relationship_type_id.label_a_b']
                : null,
            labelBToA: isset($row['relationship_type_id.label_b_a'])
                && is_string($row['relationship_type_id.label_b_a'])
                ? $row['relationship_type_id.label_b_a']
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
            'contact_id_a' => $this->contactIdA,
            'contact_id_b' => $this->contactIdB,
            'relationship_type_id' => $this->relationshipTypeId,
            'is_active' => $this->isActive,
        ];

        if ($this->startDate instanceof DateTimeImmutable) {
            $data['start_date'] = $this->startDate->format('Y-m-d');
        }

        if ($this->endDate instanceof DateTimeImmutable) {
            $data['end_date'] = $this->endDate->format('Y-m-d');
        }

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        if ($this->labelAToB !== null) {
            $data['relationship_type_id.label_a_b'] = $this->labelAToB;
        }

        if ($this->labelBToA !== null) {
            $data['relationship_type_id.label_b_a'] = $this->labelBToA;
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

    /**
     * Parses a CiviCRM date string (`'2024-01-15'` or `'2024-01-15 10:30:00'`) into
     * an immutable date, returning `null` for empty or unparsable values.
     */
    private static function toDate(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
