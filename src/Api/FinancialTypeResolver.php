<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Api;

use Woduda\CiviCRM\Contract\TransportInterface;
use Woduda\CiviCRM\Exception\ValidationException;

/**
 * Resolves CiviCRM FinancialType names to their integer IDs.
 *
 * Results are cached per instance — repeated calls for the same name do not
 * issue additional transport requests. Call {@see clearCache()} in tests that
 * need a clean state between assertions.
 *
 * Example:
 * ```php
 * $resolver = new FinancialTypeResolver($transport);
 * $id = $resolver->resolve('Donation'); // e.g. 1
 * ```
 */
final class FinancialTypeResolver
{
    /** @var array<string, int> */
    private array $cache = [];

    public function __construct(private readonly TransportInterface $transport) {}

    /**
     * Returns the CiviCRM FinancialType ID for the given type name.
     *
     * @throws ValidationException When no FinancialType with that name exists.
     */
    public function resolve(string $name): int
    {
        if (isset($this->cache[$name])) {
            return $this->cache[$name];
        }

        $values = $this->transport->send('FinancialType', 'get', [
            'where' => [['name', '=', $name]],
            'select' => ['id', 'name'],
            'limit' => 1,
        ])->values;

        if ($values === []) {
            throw ValidationException::unknownFinancialType($name);
        }

        $first = $values[0];
        $id = is_array($first) && isset($first['id']) && is_int($first['id']) ? $first['id'] : 0;

        return $this->cache[$name] = $id;
    }

    /**
     * Resolves multiple type names to their IDs.
     *
     * Names already in the cache are returned immediately; any remaining names
     * are fetched in a single batch request.
     *
     * @param  list<string>          $names
     * @return array<string, int>
     * @throws ValidationException   When any requested name does not exist.
     */
    public function resolveMany(array $names): array
    {
        $result = [];
        $missing = [];

        foreach ($names as $name) {
            if (isset($this->cache[$name])) {
                $result[$name] = $this->cache[$name];
            } else {
                $missing[] = $name;
            }
        }

        if ($missing !== []) {
            $values = $this->transport->send('FinancialType', 'get', [
                'where' => [['name', 'IN', $missing]],
                'select' => ['id', 'name'],
                'limit' => count($missing),
            ])->values;

            $found = [];
            foreach ($values as $row) {
                if (is_array($row) && isset($row['name'], $row['id']) && is_string($row['name']) && is_int($row['id'])) {
                    $this->cache[$row['name']] = $row['id'];
                    $found[] = $row['name'];
                    $result[$row['name']] = $row['id'];
                }
            }

            foreach ($missing as $name) {
                if (! in_array($name, $found, true)) {
                    throw ValidationException::unknownFinancialType($name);
                }
            }
        }

        return $result;
    }

    /**
     * Clears the in-memory cache.
     *
     * Useful in tests that need isolated resolver state between assertions.
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }
}
