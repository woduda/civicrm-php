<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Api;

use Woduda\CiviCRM\Contract\TransportInterface;
use Woduda\CiviCRM\Exception\ValidationException;
use Woduda\CiviCRM\Query\ActionRequest;
use Woduda\CiviCRM\Query\GetQuery;
use Woduda\CiviCRM\Query\Operator;

/**
 * Typed API for the CiviCRM `Contact` entity.
 *
 * Provides high-level helpers for common contact operations, including email-based
 * upsert, tag/group assignment, and validated custom-field updates.
 *
 * Example:
 * ```php
 * $contacts = $client->contacts();
 *
 * $contact = $contacts->upsertByEmail('jane@example.org', [
 *     'first_name' => 'Jane',
 *     'contact_type' => 'Individual',
 * ]);
 *
 * $contacts->withTags($contact[0]['id'], ['Donor', 'VIP']);
 * $contacts->setCustomFields($contact[0]['id'], 'Wolontariat', ['volunteer_status' => 'active']);
 * ```
 */
final readonly class ContactApi extends AbstractEntityApi
{
    public function __construct(
        TransportInterface $transport,
        private CustomFieldResolver $customFieldResolver,
    ) {
        parent::__construct($transport, 'Contact');
    }

    /**
     * Fetches contacts matching the query.
     *
     * @return array<mixed>
     *
     * Example:
     * ```php
     * $api->get(GetQuery::new()->where('contact_type', Operator::Equals, 'Individual')->limit(10));
     * ```
     */
    public function get(GetQuery $query): array
    {
        return $this->executeGet($query);
    }

    /**
     * Returns the first contact with the given ID, or `null` if not found.
     *
     * @return array<mixed>|null
     *
     * Example:
     * ```php
     * $contact = $api->getById(42);
     * ```
     */
    public function getById(int $id): ?array
    {
        $rows = $this->executeGet(
            GetQuery::new()->where('id', Operator::Equals, $id)->limit(1),
        );

        $first = $rows[0] ?? null;

        return is_array($first) ? $first : null;
    }

    /**
     * Creates a new contact with the given field values.
     *
     * @param  array<string, mixed> $values
     * @return array<mixed>
     *
     * Example:
     * ```php
     * $api->create(['contact_type' => 'Individual', 'first_name' => 'Jane']);
     * ```
     */
    public function create(array $values): array
    {
        return $this->executeAction(ActionRequest::create($this->entity, $values));
    }

    /**
     * Updates the contact identified by `$id` with the given field values.
     *
     * @param  array<string, mixed> $values
     * @return array<mixed>
     *
     * Example:
     * ```php
     * $api->update(42, ['last_name' => 'Doe']);
     * ```
     */
    public function update(int $id, array $values): array
    {
        return $this->executeAction(
            ActionRequest::update($this->entity, $values, [['id', '=', $id]]),
        );
    }

    /**
     * Finds a contact by primary email address and updates it with `$values`.
     * If no matching contact exists, creates a new one with the email merged in.
     *
     * **Note:** This is implemented as two sequential requests (Contact.get + conditional
     * Contact.create/update) and is therefore **not atomic**. A concurrent insert between
     * the get and the create may produce a duplicate. A future PR can make this atomic
     * using `Contact.save` with `match=['email_primary.email']` once `ActionRequest::save`
     * supports the `match` parameter.
     *
     * @param  array<string, mixed> $values
     * @return array<mixed>
     *
     * Example:
     * ```php
     * $api->upsertByEmail('jane@example.org', ['first_name' => 'Jane', 'contact_type' => 'Individual']);
     * ```
     */
    public function upsertByEmail(string $email, array $values): array
    {
        $existing = $this->transport->send('Contact', 'get', [
            'where' => [['email_primary.email', '=', $email]],
            'select' => ['id'],
            'limit' => 1,
        ])->values;

        $first = $existing[0] ?? null;

        if (is_array($first)) {
            $id = $first['id'] ?? null;
            return $this->update(is_int($id) ? $id : 0, $values);
        }

        return $this->create(array_merge($values, ['email' => $email]));
    }

    /**
     * Ensures every tag in `$tagNames` exists and assigns all of them to `$contactId`.
     *
     * Missing tags are created with `used_for = 'civicrm_contact'`. The assignment is
     * done via a single `EntityTag.save` with `match=[entity_id, tag_id, entity_table]`,
     * making it idempotent.
     *
     * @param list<string> $tagNames
     *
     * Example:
     * ```php
     * $api->withTags(42, ['Donor', 'VIP']);
     * ```
     */
    public function withTags(int $contactId, array $tagNames): void
    {
        if ($tagNames === []) {
            return;
        }

        $existing = $this->transport->send('Tag', 'get', [
            'where' => [['name', 'IN', $tagNames]],
            'select' => ['id', 'name'],
        ])->values;

        /** @var array<string, int> $byName */
        $byName = array_column($existing, 'id', 'name');

        $tagIds = [];

        foreach ($tagNames as $name) {
            if (isset($byName[$name])) {
                $tagIds[] = $byName[$name];
            } else {
                $created = $this->transport->send('Tag', 'create', [
                    'values' => ['name' => $name, 'used_for' => 'civicrm_contact'],
                ])->values;
                $row = $created[0] ?? null;
                $tagIds[] = is_array($row) && is_int($row['id'] ?? null) ? $row['id'] : 0;
            }
        }

        $records = array_map(
            fn(int $tagId): array => [
                'entity_id' => $contactId,
                'tag_id' => $tagId,
                'entity_table' => 'civicrm_contact',
            ],
            $tagIds,
        );

        $this->transport->send('EntityTag', 'save', [
            'records' => $records,
            'match' => ['entity_id', 'tag_id', 'entity_table'],
        ]);
    }

    /**
     * Ensures every group in `$groupTitles` exists and adds `$contactId` to each.
     *
     * Missing groups are created. Membership is written via `GroupContact.save`
     * with `match=[contact_id, group_id]`, making it idempotent.
     *
     * @param list<string> $groupTitles
     *
     * Example:
     * ```php
     * $api->addToGroups(42, ['Newsletter', 'Volunteers']);
     * ```
     */
    public function addToGroups(int $contactId, array $groupTitles): void
    {
        if ($groupTitles === []) {
            return;
        }

        $existing = $this->transport->send('Group', 'get', [
            'where' => [['title', 'IN', $groupTitles]],
            'select' => ['id', 'title'],
        ])->values;

        /** @var array<string, int> $byTitle */
        $byTitle = array_column($existing, 'id', 'title');

        $groupIds = [];

        foreach ($groupTitles as $title) {
            if (isset($byTitle[$title])) {
                $groupIds[] = $byTitle[$title];
            } else {
                $created = $this->transport->send('Group', 'create', [
                    'values' => ['title' => $title],
                ])->values;
                $row = $created[0] ?? null;
                $groupIds[] = is_array($row) && is_int($row['id'] ?? null) ? $row['id'] : 0;
            }
        }

        $records = array_map(
            fn(int $groupId): array => [
                'contact_id' => $contactId,
                'group_id' => $groupId,
                'status' => 'Added',
            ],
            $groupIds,
        );

        $this->transport->send('GroupContact', 'save', [
            'records' => $records,
            'match' => ['contact_id', 'group_id'],
        ]);
    }

    /**
     * Returns the field definitions for the Contact entity.
     *
     * @return array<mixed>
     */
    #[\Override]
    public function getFields(): array
    {
        return parent::getFields();
    }

    /**
     * Returns the available actions for the Contact entity.
     *
     * @return array<mixed>
     */
    #[\Override]
    public function getActions(): array
    {
        return parent::getActions();
    }

    /**
     * Updates custom fields on a contact after validating each field name exists.
     *
     * `$fields` maps bare field names to values; the resolver prepends `$groupName`
     * to produce the APIv4 dotted key (`"GroupName.field_name"`). Throws
     * {@see ValidationException} if any field is unknown.
     *
     * @param  array<string, mixed> $fields  Bare field name → value pairs
     * @throws ValidationException if any field does not exist in the custom group
     *
     * Example:
     * ```php
     * $api->setCustomFields(42, 'Wolontariat', ['volunteer_status' => 'active']);
     * ```
     */
    public function setCustomFields(int $contactId, string $groupName, array $fields): void
    {
        $resolved = [];

        foreach ($fields as $fieldName => $value) {
            $apiKey = $this->customFieldResolver->resolve($groupName, $fieldName);
            $resolved[$apiKey] = $value;
        }

        $this->executeAction(
            ActionRequest::update($this->entity, $resolved, [['id', '=', $contactId]]),
        );
    }
}
