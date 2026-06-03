<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Api;

use Woduda\CiviCRM\Contract\TransportInterface;

/**
 * Typed API for the CiviCRM `Group` entity and related `GroupContact` membership.
 *
 * Example:
 * ```php
 * $groups = $client->groups();
 *
 * $groupId = $groups->ensureExists('Newsletter');
 * $groups->addContact(42, $groupId);
 * $groups->removeContact(42, $groupId);
 * ```
 */
final readonly class GroupApi extends AbstractEntityApi
{
    public function __construct(TransportInterface $transport)
    {
        parent::__construct($transport, 'Group');
    }

    /**
     * Returns the field definitions for the Group entity.
     *
     * @return array<mixed>
     */
    #[\Override]
    public function getFields(): array
    {
        return parent::getFields();
    }

    /**
     * Returns the available actions for the Group entity.
     *
     * @return array<mixed>
     */
    #[\Override]
    public function getActions(): array
    {
        return parent::getActions();
    }

    /**
     * Returns the ID of a group matching `$title`, creating it if it does not exist.
     *
     * Example:
     * ```php
     * $groupId = $api->ensureExists('Newsletter');
     * ```
     */
    public function ensureExists(string $title): int
    {
        $rows = $this->transport->send('Group', 'get', [
            'where' => [['title', '=', $title]],
            'select' => ['id'],
            'limit' => 1,
        ])->values;

        $first = $rows[0] ?? null;

        if (is_array($first)) {
            $id = $first['id'] ?? null;
            return is_int($id) ? $id : 0;
        }

        $created = $this->transport->send('Group', 'create', [
            'values' => ['title' => $title],
        ])->values;

        $row = $created[0] ?? null;
        return is_array($row) && is_int($row['id'] ?? null) ? $row['id'] : 0;
    }

    /**
     * Adds the contact to the group with `status = 'Added'`.
     *
     * Idempotent — uses `GroupContact.save` with `match=[contact_id, group_id]`.
     *
     * Example:
     * ```php
     * $api->addContact(42, $groupId);
     * ```
     */
    public function addContact(int $contactId, int $groupId): void
    {
        $this->transport->send('GroupContact', 'save', [
            'records' => [[
                'contact_id' => $contactId,
                'group_id' => $groupId,
                'status' => 'Added',
            ]],
            'match' => ['contact_id', 'group_id'],
        ]);
    }

    /**
     * Sets the contact's `GroupContact` status to `'Removed'`.
     *
     * CiviCRM tracks group removals for audit purposes — this method updates the
     * membership record rather than deleting it, preserving the removal history.
     *
     * Example:
     * ```php
     * $api->removeContact(42, $groupId);
     * ```
     */
    public function removeContact(int $contactId, int $groupId): void
    {
        $this->transport->send('GroupContact', 'update', [
            'values' => ['status' => 'Removed'],
            'where' => [
                ['contact_id', '=', $contactId],
                ['group_id', '=', $groupId],
            ],
        ]);
    }
}
