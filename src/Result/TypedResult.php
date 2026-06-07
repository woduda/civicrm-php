<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Result;

use Woduda\CiviCRM\Entity\FromArrayInterface;

/**
 * Hydrates raw APIv4 rows into typed entity DTOs.
 */
final readonly class TypedResult
{
    private function __construct() {}

    /**
     * @template TRow
     * @template T of FromArrayInterface
     *
     * @param Result<TRow>    $result
     * @param class-string<T>   $class
     * @return Result<T>
     */
    public static function hydrate(Result $result, string $class): Result
    {
        $hydrated = [];

        foreach ($result->values as $row) {
            if (! is_array($row)) {
                continue;
            }

            $normalized = [];

            foreach ($row as $key => $value) {
                if (is_string($key)) {
                    $normalized[$key] = $value;
                }
            }

            /** @var T $entity */
            $entity = $class::fromArray($normalized);
            $hydrated[] = $entity;
        }

        return new Result($hydrated, $result->count);
    }
}
