<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Entity;

/**
 * Typed representation of a CiviCRM Email record.
 */
final readonly class Email implements FromArrayInterface
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
        public string $email,
        public ?bool $onHold,
        public array $rawData,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        $onHold = null;
        if (array_key_exists('on_hold', $row)) {
            $onHold = self::toBool($row['on_hold']);
        }

        return new self(
            id: self::toInt($row['id'] ?? null),
            contactId: self::toInt($row['contact_id'] ?? null),
            locationType: isset($row['location_type_id.name']) && is_string($row['location_type_id.name'])
                ? $row['location_type_id.name']
                : '',
            isPrimary: self::toBool($row['is_primary'] ?? false),
            isBilling: self::toBool($row['is_billing'] ?? false),
            email: isset($row['email']) && is_string($row['email']) ? $row['email'] : '',
            onHold: $onHold,
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
            'email' => $this->email,
            'is_primary' => $this->isPrimary,
            'is_billing' => $this->isBilling,
        ];

        if ($this->locationType !== '') {
            $data['location_type_id.name'] = $this->locationType;
        }

        if ($this->onHold !== null) {
            $data['on_hold'] = $this->onHold;
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
