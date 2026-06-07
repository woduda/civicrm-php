<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Api;

use Woduda\CiviCRM\Contract\TransportInterface;
use Woduda\CiviCRM\Entity\Email;
use Woduda\CiviCRM\Query\GetQuery;
use Woduda\CiviCRM\Result\Result;
use Woduda\CiviCRM\Result\TypedResult;

/**
 * Typed API for the CiviCRM `Email` entity.
 *
 * Example:
 * ```php
 * $emails = $client->emails();
 *
 * $all = $emails->forContact(42);
 * $primary = $emails->primary(42);
 * $emails->setPrimary(101);
 * $emails->add(42, 'jane@example.org', isPrimary: true);
 * ```
 */
final readonly class EmailApi extends AbstractContactSubEntityApi
{
    public function __construct(TransportInterface $transport)
    {
        parent::__construct($transport, 'Email');
    }

    /**
     * Fetches email records matching the query.
     *
     * @return Result<Email>
     *
     * Example:
     * ```php
     * $api->get(GetQuery::new()->where('email', Operator::Equals, 'jane@example.org'));
     * ```
     */
    public function get(GetQuery $query): Result
    {
        return TypedResult::hydrate($this->executeGet($query), Email::class);
    }

    /**
     * Returns all email records for a contact, primary first.
     *
     * @return Result<Email>
     *
     * Example:
     * ```php
     * $emails = $api->forContact(42);
     * ```
     */
    public function forContact(int $contactId): Result
    {
        return TypedResult::hydrate(
            $this->executeGet($this->contactQuery($contactId)),
            Email::class,
        );
    }

    /**
     * Returns the primary email for a contact, or `null` if none is marked primary.
     *
     * Example:
     * ```php
     * $email = $api->primary(42);
     * ```
     */
    public function primary(int $contactId): ?Email
    {
        foreach ($this->forContact($contactId)->values as $email) {
            if ($email instanceof Email && $email->isPrimary) {
                return $email;
            }
        }

        return null;
    }

    /**
     * Marks the given email as primary.
     *
     * CiviCRM automatically unsets `is_primary` on all other email records for
     * that contact — no separate update calls are required.
     *
     * Example:
     * ```php
     * $api->setPrimary(101);
     * ```
     */
    #[\Override]
    public function setPrimary(int $id): void
    {
        parent::setPrimary($id);
    }

    /**
     * Creates a new email record for a contact.
     *
     * Example:
     * ```php
     * $email = $api->add(42, 'jane@example.org', 'Home', isPrimary: true);
     * ```
     */
    public function add(
        int $contactId,
        string $email,
        string $locationType = 'Home',
        bool $isPrimary = false,
        ?bool $onHold = null,
    ): Email {
        $values = [
            'contact_id' => $contactId,
            'email' => $email,
            'location_type_id.name' => $locationType,
            'is_primary' => $isPrimary,
        ];

        if ($onHold !== null) {
            $values['on_hold'] = $onHold;
        }

        $record = $this->createRecord($values);

        assert($record instanceof Email);

        return $record;
    }

    /**
     * Deletes the email record with the given ID.
     *
     * Example:
     * ```php
     * $api->remove(101);
     * ```
     */
    #[\Override]
    public function remove(int $id): void
    {
        parent::remove($id);
    }

    /**
     * Updates a record by ID and returns the hydrated email.
     *
     * @param  array<string, mixed> $values
     */
    #[\Override]
    public function updateById(int $id, array $values): Email
    {
        $record = parent::updateById($id, $values);

        assert($record instanceof Email);

        return $record;
    }

    /**
     * Returns the field definitions for the Email entity.
     *
     * @return array<mixed>
     */
    #[\Override]
    public function getFields(): array
    {
        return parent::getFields();
    }

    /**
     * Returns the available actions for the Email entity.
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
        return Email::class;
    }
}
