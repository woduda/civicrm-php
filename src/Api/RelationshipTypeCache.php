<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Api;

use Woduda\CiviCRM\Entity\RelationshipType;
use Woduda\CiviCRM\Result\Result;

/**
 * Mutable in-memory holder for the memoized list of relationship types.
 *
 * This is intentionally **not** a `final readonly` value object: it exists so that
 * {@see RelationshipTypeApi} — which is `final readonly` like every other entity API —
 * can memoize the result of `all()`. PHP's `readonly` only forbids reassigning the
 * property that points at this holder; mutating the object behind that reference is
 * allowed, which is exactly the memoization seam we need without weakening the
 * immutability of the API class itself.
 */
final class RelationshipTypeCache
{
    /** @var Result<RelationshipType>|null */
    private ?Result $types = null;

    /**
     * @return Result<RelationshipType>|null
     */
    public function get(): ?Result
    {
        return $this->types;
    }

    /**
     * @param Result<RelationshipType> $types
     */
    public function set(Result $types): void
    {
        $this->types = $types;
    }

    public function clear(): void
    {
        $this->types = null;
    }
}
