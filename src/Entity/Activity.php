<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Entity;

/**
 * Typed representation of a CiviCRM Activity record.
 */
final readonly class Activity implements FromArrayInterface
{
    /**
     * @param array<string, mixed> $rawData Full original APIv4 row
     */
    public function __construct(
        public ?int $id,
        public ?string $subject,
        public ?string $activityType,
        public ?int $sourceContactId,
        public ?string $status,
        public array $rawData,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            id: isset($row['id']) && is_int($row['id']) ? $row['id'] : null,
            subject: isset($row['subject']) && is_string($row['subject']) ? $row['subject'] : null,
            activityType: isset($row['activity_type_id.name']) && is_string($row['activity_type_id.name'])
                ? $row['activity_type_id.name']
                : null,
            sourceContactId: isset($row['source_contact_id']) && is_int($row['source_contact_id'])
                ? $row['source_contact_id']
                : null,
            status: isset($row['status_id.name']) && is_string($row['status_id.name'])
                ? $row['status_id.name']
                : null,
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

        if ($this->subject !== null) {
            $data['subject'] = $this->subject;
        }

        if ($this->activityType !== null) {
            $data['activity_type_id.name'] = $this->activityType;
        }

        if ($this->sourceContactId !== null) {
            $data['source_contact_id'] = $this->sourceContactId;
        }

        if ($this->status !== null) {
            $data['status_id.name'] = $this->status;
        }

        return $data;
    }
}
