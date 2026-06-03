<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Api;

use Woduda\CiviCRM\Query\ActionRequest;
use Woduda\CiviCRM\Query\GetQuery;

/**
 * Generic CiviCRM APIv4 entity API for arbitrary entities.
 *
 * Use {@see \Woduda\CiviCRM\CiviCrmClient::entity()} to obtain an instance.
 */
final readonly class GenericApi extends AbstractEntityApi
{
    /**
     * Fetches records matching the query.
     *
     * @return array<mixed>
     */
    public function get(GetQuery $query): array
    {
        return $this->executeGet($query);
    }

    /**
     * Creates a new record with the given field values.
     *
     * @param  array<string, mixed> $values
     * @return array<mixed>
     */
    public function create(array $values): array
    {
        return $this->executeAction(ActionRequest::create($this->entity, $values));
    }

    /**
     * Updates records matching the given where condition.
     *
     * @param  array<string, mixed>  $values
     * @param  GetQuery|array<mixed> $where  Raw APIv4 where clauses, or a GetQuery whose
     *                                       where conditions are extracted
     * @return array<mixed>
     */
    public function update(array $values, GetQuery|array $where): array
    {
        return $this->executeAction(
            ActionRequest::update($this->entity, $values, $this->resolveWhere($where)),
        );
    }

    /**
     * Saves (bulk upserts) the given list of records.
     *
     * @param  list<array<string, mixed>> $records
     * @return array<mixed>
     */
    public function save(array $records): array
    {
        return $this->executeAction(ActionRequest::save($this->entity, $records));
    }

    /**
     * Deletes records matching the given where condition.
     *
     * @param  GetQuery|array<mixed> $where  Raw APIv4 where clauses, or a GetQuery whose
     *                                       where conditions are extracted
     * @return array<mixed>
     */
    public function delete(GetQuery|array $where): array
    {
        return $this->executeAction(
            ActionRequest::delete($this->entity, $this->resolveWhere($where)),
        );
    }

    /**
     * Returns the field definitions for this entity.
     *
     * @return array<mixed>
     */
    #[\Override]
    public function getFields(): array
    {
        return parent::getFields();
    }

    /**
     * Returns the available actions for this entity.
     *
     * @return array<mixed>
     */
    #[\Override]
    public function getActions(): array
    {
        return parent::getActions();
    }

    /**
     * Normalises a where argument to an APIv4 where-clauses array.
     *
     * If a {@see GetQuery} is given, its compiled `where` clauses are extracted.
     * An empty GetQuery (no conditions) resolves to an empty array.
     *
     * @param  GetQuery|array<mixed> $where
     * @return array<mixed>
     */
    private function resolveWhere(GetQuery|array $where): array
    {
        if ($where instanceof GetQuery) {
            $params = $where->toParams();
            $clauses = $params['where'] ?? null;

            return is_array($clauses) ? $clauses : [];
        }

        return $where;
    }
}
