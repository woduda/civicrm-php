<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Result;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Iterable collection of APIv4 records with a server-reported total count.
 *
 * @template T
 * @implements IteratorAggregate<int, T>
 */
final readonly class Result implements Countable, IteratorAggregate
{
    /**
     * @param list<T>    $values Hydrated or raw record rows
     * @param int<0, max> $count Server-reported total record count
     */
    public function __construct(
        public array $values,
        public int $count,
    ) {}

    /**
     * @return Result<array<string, mixed>>
     */
    public static function fromApiResponse(ApiResponse $response): self
    {
        $raw = array_is_list($response->values)
            ? $response->values
            : array_values($response->values);

        $values = [];

        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }

            $normalized = [];

            foreach ($row as $key => $value) {
                if (is_string($key)) {
                    $normalized[$key] = $value;
                }
            }

            $values[] = $normalized;
        }

        return new self($values, max(0, $response->count));
    }

    /**
     * @return ArrayIterator<int, T>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->values);
    }

    public function count(): int
    {
        return $this->count;
    }

    /**
     * @return T|null
     */
    public function first(): mixed
    {
        return $this->values[0] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->count === 0;
    }

    /**
     * @template U
     *
     * @param callable(T): U $callback
     * @return Result<U>
     */
    public function map(callable $callback): self
    {
        return new self(
            array_map($callback, $this->values),
            $this->count,
        );
    }

    /**
     * @param callable(T): bool $callback
     * @return Result<T>
     */
    public function filter(callable $callback): self
    {
        $filtered = array_values(array_filter($this->values, $callback));

        return new self($filtered, count($filtered));
    }
}
