<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Api;

use Woduda\CiviCRM\Contract\TransportInterface;

/**
 * Typed API for the CiviCRM `Tag` entity and related `EntityTag` assignments.
 *
 * Example:
 * ```php
 * $tags = $client->tags();
 *
 * $id = $tags->ensureExists('VIP');
 * $tags->tagContact(42, 'VIP');
 * ```
 */
final readonly class TagApi extends AbstractEntityApi
{
    public function __construct(TransportInterface $transport)
    {
        parent::__construct($transport, 'Tag');
    }

    /**
     * Returns the ID of a tag matching `$name`, creating it if it does not exist.
     *
     * Created tags have `used_for = 'civicrm_contact'`.
     *
     * Example:
     * ```php
     * $tagId = $api->ensureExists('VIP');
     * ```
     */
    public function ensureExists(string $name): int
    {
        $rows = $this->transport->send('Tag', 'get', [
            'where' => [['name', '=', $name]],
            'select' => ['id'],
            'limit' => 1,
        ])->values;

        $first = $rows[0] ?? null;

        if (is_array($first)) {
            $id = $first['id'] ?? null;
            return is_int($id) ? $id : 0;
        }

        $created = $this->transport->send('Tag', 'create', [
            'values' => ['name' => $name, 'used_for' => 'civicrm_contact'],
        ])->values;

        $row = $created[0] ?? null;
        return is_array($row) && is_int($row['id'] ?? null) ? $row['id'] : 0;
    }

    /**
     * Returns the field definitions for the Tag entity.
     *
     * @return array<mixed>
     */
    #[\Override]
    public function getFields(): array
    {
        return parent::getFields();
    }

    /**
     * Returns the available actions for the Tag entity.
     *
     * @return array<mixed>
     */
    #[\Override]
    public function getActions(): array
    {
        return parent::getActions();
    }

    /**
     * Ensures `$tagName` exists and assigns it to `$contactId` via `EntityTag`.
     *
     * Idempotent — uses `EntityTag.save` with `match=[entity_id, tag_id, entity_table]`
     * so calling this multiple times for the same contact/tag pair is safe.
     *
     * Example:
     * ```php
     * $api->tagContact(42, 'VIP');
     * ```
     */
    public function tagContact(int $contactId, string $tagName): void
    {
        $tagId = $this->ensureExists($tagName);

        $this->transport->send('EntityTag', 'save', [
            'records' => [[
                'entity_id' => $contactId,
                'tag_id' => $tagId,
                'entity_table' => 'civicrm_contact',
            ]],
            'match' => ['entity_id', 'tag_id', 'entity_table'],
        ]);
    }
}
