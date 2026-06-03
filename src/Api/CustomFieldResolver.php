<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Api;

use Woduda\CiviCRM\Contract\TransportInterface;
use Woduda\CiviCRM\Exception\ValidationException;

/**
 * Validates and resolves CiviCRM custom field names to their APIv4 dotted key.
 *
 * In CiviCRM APIv4, custom fields are addressed as "GroupName.field_name" — both
 * in `select` arrays and in `values` maps for create/update. This class verifies
 * that a given group/field combination exists (via `CustomField.get`), caches the
 * result per instance, and throws {@see ValidationException} when the field is absent.
 *
 * Example:
 * ```php
 * $resolver = new CustomFieldResolver($transport);
 * $key = $resolver->resolve('Wolontariat', 'volunteer_status');
 * // returns 'Wolontariat.volunteer_status'
 * ```
 */
final class CustomFieldResolver
{
    /** @var array<string, string> */
    private array $cache = [];

    public function __construct(private readonly TransportInterface $transport) {}

    /**
     * Returns the APIv4 field key "GroupName.field_name" after verifying the field exists.
     *
     * The result is cached per instance — repeated calls for the same group/field do not
     * issue additional transport requests.
     *
     * @throws ValidationException if the custom field does not exist in CiviCRM
     */
    public function resolve(string $groupName, string $fieldName): string
    {
        $cacheKey = "{$groupName}.{$fieldName}";

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $fields = $this->transport->send('CustomField', 'get', [
            'where' => [
                ['custom_group_id.name', '=', $groupName],
                ['name', '=', $fieldName],
            ],
            'select' => ['id', 'name'],
            'limit' => 1,
        ])->values;

        if ($fields === []) {
            throw ValidationException::unknownCustomField($groupName, $fieldName);
        }

        return $this->cache[$cacheKey] = $cacheKey;
    }
}
