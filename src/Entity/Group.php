<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Entity;

/**
 * Typed representation of a CiviCRM Group record.
 */
final readonly class Group implements FromArrayInterface
{
    /**
     * @param array<string, mixed> $rawData Full original APIv4 row
     */
    public function __construct(
        public ?int $id,
        public ?string $title,
        public ?string $groupType,
        public array $rawData,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            id: isset($row['id']) && is_int($row['id']) ? $row['id'] : null,
            title: isset($row['title']) && is_string($row['title']) ? $row['title'] : null,
            groupType: isset($row['group_type']) && is_string($row['group_type']) ? $row['group_type'] : null,
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

        if ($this->title !== null) {
            $data['title'] = $this->title;
        }

        if ($this->groupType !== null) {
            $data['group_type'] = $this->groupType;
        }

        return $data;
    }
}
