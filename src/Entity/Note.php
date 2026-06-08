<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Entity;

use DateTimeImmutable;

/**
 * Typed representation of a CiviCRM Note record.
 */
final readonly class Note implements FromArrayInterface
{
    /**
     * @param array<string, mixed> $rawData Full original APIv4 row
     */
    public function __construct(
        public int $id,
        public string $entityTable,
        public int $entityId,
        public ?string $subject,
        public string $note,
        public ?string $privacy,
        public DateTimeImmutable $modifiedDate,
        public ?int $contactIdCreator,
        public array $rawData,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        $modifiedDate = null;
        if (isset($row['modified_date']) && is_string($row['modified_date'])) {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $row['modified_date']);
            $modifiedDate = $parsed !== false ? $parsed : null;
        }

        return new self(
            id: isset($row['id']) && is_int($row['id']) ? $row['id'] : 0,
            entityTable: isset($row['entity_table']) && is_string($row['entity_table']) ? $row['entity_table'] : '',
            entityId: isset($row['entity_id']) && is_int($row['entity_id']) ? $row['entity_id'] : 0,
            subject: isset($row['subject']) && is_string($row['subject']) ? $row['subject'] : null,
            note: isset($row['note']) && is_string($row['note']) ? $row['note'] : '',
            privacy: isset($row['privacy']) && is_string($row['privacy']) ? $row['privacy'] : null,
            modifiedDate: $modifiedDate ?? new DateTimeImmutable('1970-01-01 00:00:00'),
            contactIdCreator: isset($row['contact_id']) && is_int($row['contact_id']) ? $row['contact_id'] : null,
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
            'entity_table' => $this->entityTable,
            'entity_id' => $this->entityId,
            'note' => $this->note,
            'modified_date' => $this->modifiedDate->format('Y-m-d H:i:s'),
        ];

        if ($this->subject !== null) {
            $data['subject'] = $this->subject;
        }

        if ($this->privacy !== null) {
            $data['privacy'] = $this->privacy;
        }

        if ($this->contactIdCreator !== null) {
            $data['contact_id'] = $this->contactIdCreator;
        }

        return $data;
    }
}
