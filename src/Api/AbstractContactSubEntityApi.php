<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Api;

use Woduda\CiviCRM\Entity\FromArrayInterface;
use Woduda\CiviCRM\Exception\ValidationException;
use Woduda\CiviCRM\Query\ActionRequest;
use Woduda\CiviCRM\Query\GetQuery;
use Woduda\CiviCRM\Query\Operator;
use Woduda\CiviCRM\Result\TypedResult;

/**
 * Shared logic for CiviCRM contact sub-entities (Email, Phone, Address).
 */
abstract readonly class AbstractContactSubEntityApi extends AbstractEntityApi
{
    /**
     * @return class-string<FromArrayInterface>
     */
    abstract protected function dtoClass(): string;

    /**
     * Builds a query for all records belonging to a contact, primary first.
     */
    protected function contactQuery(int $contactId): GetQuery
    {
        return GetQuery::new()
            ->where('contact_id', Operator::Equals, $contactId)
            ->orderBy('is_primary', 'DESC');
    }

    /**
     * Marks the given record as primary.
     *
     * CiviCRM automatically unsets `is_primary` on all other records of the same
     * entity for that contact — no separate update calls are required.
     */
    public function setPrimary(int $id): void
    {
        $this->executeAction(
            ActionRequest::update($this->entity, ['is_primary' => true], [['id', '=', $id]]),
        );
    }

    /**
     * Deletes the record with the given ID.
     */
    public function remove(int $id): void
    {
        $this->executeAction(
            ActionRequest::delete($this->entity, [['id', '=', $id]]),
        );
    }

    /**
     * Updates a record by ID and returns the hydrated DTO.
     *
     * @param  array<string, mixed> $values
     */
    protected function updateById(int $id, array $values): FromArrayInterface
    {
        $result = TypedResult::hydrate(
            $this->executeAction(
                ActionRequest::update($this->entity, $values, [['id', '=', $id]]),
            ),
            $this->dtoClass(),
        );

        $first = $result->first();

        if (! $first instanceof FromArrayInterface) {
            throw ValidationException::emptyApiResult($this->entity, 'update');
        }

        return $first;
    }

    /**
     * Creates a record and returns the hydrated DTO.
     *
     * @param  array<string, mixed> $values
     */
    protected function createRecord(array $values): FromArrayInterface
    {
        $result = TypedResult::hydrate(
            $this->executeAction(ActionRequest::create($this->entity, $values)),
            $this->dtoClass(),
        );

        $first = $result->first();

        if (! $first instanceof FromArrayInterface) {
            throw ValidationException::emptyApiResult($this->entity, 'create');
        }

        return $first;
    }
}
