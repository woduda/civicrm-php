<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Entity;

/**
 * Typed representation of a CiviCRM Tag record.
 */
final readonly class Tag implements FromArrayInterface
{
    /**
     * @param array<string, mixed> $rawData Full original APIv4 row
     */
    public function __construct(
        public ?int $id,
        public ?string $name,
        public ?string $usedFor,
        public array $rawData,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            id: isset($row['id']) && is_int($row['id']) ? $row['id'] : null,
            name: isset($row['name']) && is_string($row['name']) ? $row['name'] : null,
            usedFor: isset($row['used_for']) && is_string($row['used_for']) ? $row['used_for'] : null,
            rawData: $row,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];

        if ($this->id !== null) {
            $data['id'] = $this->id;
        }

        if ($this->name !== null) {
            $data['name'] = $this->name;
        }

        if ($this->usedFor !== null) {
            $data['used_for'] = $this->usedFor;
        }

        return $data;
    }
}
