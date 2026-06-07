<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Api;

use Woduda\CiviCRM\Contract\TransportInterface;
use Woduda\CiviCRM\Entity\Phone;
use Woduda\CiviCRM\Query\GetQuery;
use Woduda\CiviCRM\Result\Result;
use Woduda\CiviCRM\Result\TypedResult;

/**
 * Typed API for the CiviCRM `Phone` entity.
 *
 * Example:
 * ```php
 * $phones = $client->phones();
 *
 * $all = $phones->forContact(42);
 * $primary = $phones->primary(42);
 * $phones->add(42, '+48123456789', 'Mobile', isPrimary: true);
 * ```
 */
final readonly class PhoneApi extends AbstractContactSubEntityApi
{
    public function __construct(TransportInterface $transport)
    {
        parent::__construct($transport, 'Phone');
    }

    /**
     * Fetches phone records matching the query.
     *
     * @return Result<Phone>
     *
     * Example:
     * ```php
     * $api->get(GetQuery::new()->where('phone', Operator::Like, '%123%'));
     * ```
     */
    public function get(GetQuery $query): Result
    {
        return TypedResult::hydrate($this->executeGet($query), Phone::class);
    }

    /**
     * Returns all phone records for a contact, primary first.
     *
     * @return Result<Phone>
     *
     * Example:
     * ```php
     * $phones = $api->forContact(42);
     * ```
     */
    public function forContact(int $contactId): Result
    {
        return TypedResult::hydrate(
            $this->executeGet($this->contactQuery($contactId)),
            Phone::class,
        );
    }

    /**
     * Returns the primary phone for a contact, or `null` if none is marked primary.
     *
     * Example:
     * ```php
     * $phone = $api->primary(42);
     * ```
     */
    public function primary(int $contactId): ?Phone
    {
        foreach ($this->forContact($contactId)->values as $phone) {
            if ($phone instanceof Phone && $phone->isPrimary) {
                return $phone;
            }
        }

        return null;
    }

    /**
     * Marks the given phone as primary.
     *
     * CiviCRM automatically unsets `is_primary` on all other phone records for
     * that contact — no separate update calls are required.
     *
     * Example:
     * ```php
     * $api->setPrimary(201);
     * ```
     */
    #[\Override]
    public function setPrimary(int $id): void
    {
        parent::setPrimary($id);
    }

    /**
     * Creates a new phone record for a contact.
     *
     * Example:
     * ```php
     * $phone = $api->add(42, '+48123456789', 'Mobile', 'Home', isPrimary: true);
     * ```
     */
    public function add(
        int $contactId,
        string $phone,
        ?string $phoneType = null,
        string $locationType = 'Home',
        bool $isPrimary = false,
    ): Phone {
        $values = [
            'contact_id' => $contactId,
            'phone' => $phone,
            'location_type_id.name' => $locationType,
            'is_primary' => $isPrimary,
        ];

        if ($phoneType !== null) {
            $values['phone_type_id.name'] = $phoneType;
        }

        $record = $this->createRecord($values);

        assert($record instanceof Phone);

        return $record;
    }

    /**
     * Deletes the phone record with the given ID.
     *
     * Example:
     * ```php
     * $api->remove(201);
     * ```
     */
    #[\Override]
    public function remove(int $id): void
    {
        parent::remove($id);
    }

    /**
     * Updates a record by ID and returns the hydrated phone.
     *
     * @param  array<string, mixed> $values
     */
    #[\Override]
    public function updateById(int $id, array $values): Phone
    {
        $record = parent::updateById($id, $values);

        assert($record instanceof Phone);

        return $record;
    }

    /**
     * Returns the field definitions for the Phone entity.
     *
     * @return array<mixed>
     */
    #[\Override]
    public function getFields(): array
    {
        return parent::getFields();
    }

    /**
     * Returns the available actions for the Phone entity.
     *
     * @return array<mixed>
     */
    #[\Override]
    public function getActions(): array
    {
        return parent::getActions();
    }

    #[\Override]
    protected function dtoClass(): string
    {
        return Phone::class;
    }
}
