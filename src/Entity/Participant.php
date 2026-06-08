<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Entity;

use DateTimeImmutable;

/**
 * Typed representation of a CiviCRM Participant record.
 *
 * `$status` is hydrated from `status_id:name` (string) when present in the API
 * response; otherwise from `status_id` (int) using the default CiviCRM ID map.
 * When neither key is available the status defaults to
 * {@see ParticipantStatus::Registered}.
 */
final readonly class Participant implements FromArrayInterface
{
    /**
     * @param array<string, mixed> $rawData Full original APIv4 row
     */
    public function __construct(
        public int $id,
        public int $contactId,
        public int $eventId,
        public ParticipantStatus $status,
        public ?int $roleId,
        public ?DateTimeImmutable $registerDate,
        public ?string $source,
        public array $rawData,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        $registerDate = null;
        if (isset($row['register_date']) && is_string($row['register_date'])) {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $row['register_date']);
            $registerDate = $parsed !== false ? $parsed : null;
        }

        return new self(
            id: isset($row['id']) && is_int($row['id']) ? $row['id'] : 0,
            contactId: isset($row['contact_id']) && is_int($row['contact_id']) ? $row['contact_id'] : 0,
            eventId: isset($row['event_id']) && is_int($row['event_id']) ? $row['event_id'] : 0,
            status: self::hydrateStatus($row),
            roleId: isset($row['role_id']) && is_int($row['role_id']) ? $row['role_id'] : null,
            registerDate: $registerDate,
            source: isset($row['source']) && is_string($row['source']) ? $row['source'] : null,
            rawData: $row,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function hydrateStatus(array $row): ParticipantStatus
    {
        if (isset($row['status_id:name']) && is_string($row['status_id:name'])) {
            $status = ParticipantStatus::tryFrom($row['status_id:name']);
            if ($status !== null) {
                return $status;
            }
        }

        if (isset($row['status_id']) && is_int($row['status_id'])) {
            try {
                return ParticipantStatus::fromId($row['status_id']);
            } catch (\ValueError) {
                // Unknown custom installation ID — fall through to default.
            }
        }

        return ParticipantStatus::Registered;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'contact_id' => $this->contactId,
            'event_id' => $this->eventId,
            'status_id:name' => $this->status->value,
        ];

        if ($this->roleId !== null) {
            $data['role_id'] = $this->roleId;
        }

        if ($this->registerDate instanceof \DateTimeImmutable) {
            $data['register_date'] = $this->registerDate->format('Y-m-d H:i:s');
        }

        if ($this->source !== null) {
            $data['source'] = $this->source;
        }

        return $data;
    }
}
