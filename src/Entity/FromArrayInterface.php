<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Entity;

/**
 * Contract for entity DTOs that can be hydrated from a CiviCRM APIv4 row.
 */
interface FromArrayInterface
{
    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
