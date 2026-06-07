<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Entity;

/**
 * Typed representation of a CiviCRM Contact record.
 */
final readonly class Contact implements FromArrayInterface
{
    /**
     * @param array<string, mixed> $rawData Full original APIv4 row
     */
    public function __construct(
        public ?int $id,
        public ?string $contactType,
        public ?string $displayName,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $email,
        public array $rawData,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        $email = null;
        if (isset($row['email_primary.email']) && is_string($row['email_primary.email'])) {
            $email = $row['email_primary.email'];
        } elseif (isset($row['email']) && is_string($row['email'])) {
            $email = $row['email'];
        }

        return new self(
            id: isset($row['id']) && is_int($row['id']) ? $row['id'] : null,
            contactType: isset($row['contact_type']) && is_string($row['contact_type']) ? $row['contact_type'] : null,
            displayName: isset($row['display_name']) && is_string($row['display_name']) ? $row['display_name'] : null,
            firstName: isset($row['first_name']) && is_string($row['first_name']) ? $row['first_name'] : null,
            lastName: isset($row['last_name']) && is_string($row['last_name']) ? $row['last_name'] : null,
            email: $email,
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

        if ($this->contactType !== null) {
            $data['contact_type'] = $this->contactType;
        }

        if ($this->displayName !== null) {
            $data['display_name'] = $this->displayName;
        }

        if ($this->firstName !== null) {
            $data['first_name'] = $this->firstName;
        }

        if ($this->lastName !== null) {
            $data['last_name'] = $this->lastName;
        }

        if ($this->email !== null) {
            $data['email'] = $this->email;
        }

        return $data;
    }
}
