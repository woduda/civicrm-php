<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Entity;

use DateTimeImmutable;

/**
 * Typed representation of a CiviCRM Event record.
 */
final readonly class Event implements FromArrayInterface
{
    /**
     * @param array<string, mixed> $rawData Full original APIv4 row
     */
    public function __construct(
        public int $id,
        public string $title,
        public ?string $summary,
        public ?string $description,
        public DateTimeImmutable $startDate,
        public ?DateTimeImmutable $endDate,
        public int $eventTypeId,
        public bool $isActive,
        public bool $isPublic,
        public ?int $maxParticipants,
        public ?int $defaultRoleId,
        public array $rawData,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        $startDate = null;
        if (isset($row['start_date']) && is_string($row['start_date'])) {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $row['start_date']);
            $startDate = $parsed !== false ? $parsed : null;
        }

        $endDate = null;
        if (isset($row['end_date']) && is_string($row['end_date'])) {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $row['end_date']);
            $endDate = $parsed !== false ? $parsed : null;
        }

        return new self(
            id: isset($row['id']) && is_int($row['id']) ? $row['id'] : 0,
            title: isset($row['title']) && is_string($row['title']) ? $row['title'] : '',
            summary: isset($row['summary']) && is_string($row['summary']) ? $row['summary'] : null,
            description: isset($row['description']) && is_string($row['description']) ? $row['description'] : null,
            startDate: $startDate ?? new DateTimeImmutable('1970-01-01 00:00:00'),
            endDate: $endDate,
            eventTypeId: isset($row['event_type_id']) && is_int($row['event_type_id']) ? $row['event_type_id'] : 0,
            isActive: isset($row['is_active']) && is_bool($row['is_active']) && $row['is_active'],
            isPublic: isset($row['is_public']) && is_bool($row['is_public']) && $row['is_public'],
            maxParticipants: isset($row['max_participants']) && is_int($row['max_participants']) ? $row['max_participants'] : null,
            defaultRoleId: isset($row['default_role_id']) && is_int($row['default_role_id']) ? $row['default_role_id'] : null,
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
            'title' => $this->title,
            'start_date' => $this->startDate->format('Y-m-d H:i:s'),
            'event_type_id' => $this->eventTypeId,
            'is_active' => $this->isActive,
            'is_public' => $this->isPublic,
        ];

        if ($this->summary !== null) {
            $data['summary'] = $this->summary;
        }

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        if ($this->endDate instanceof \DateTimeImmutable) {
            $data['end_date'] = $this->endDate->format('Y-m-d H:i:s');
        }

        if ($this->maxParticipants !== null) {
            $data['max_participants'] = $this->maxParticipants;
        }

        if ($this->defaultRoleId !== null) {
            $data['default_role_id'] = $this->defaultRoleId;
        }

        return $data;
    }
}
