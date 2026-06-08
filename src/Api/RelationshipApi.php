<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Api;

use DateTimeImmutable;
use Woduda\CiviCRM\Contract\TransportInterface;
use Woduda\CiviCRM\Entity\Relationship;
use Woduda\CiviCRM\Entity\RelationshipType;
use Woduda\CiviCRM\Query\ActionRequest;
use Woduda\CiviCRM\Query\GetQuery;
use Woduda\CiviCRM\Query\Operator;
use Woduda\CiviCRM\Result\Result;
use Woduda\CiviCRM\Result\TypedResult;

/**
 * Typed API for the CiviCRM `Relationship` entity (links between two contacts).
 *
 * A relationship is directional — contact A → contact B under a
 * {@see \Woduda\CiviCRM\Entity\RelationshipType}. For the "Reports to" / "Manages"
 * type, `create($employeeId, $managerId, 'Reports to')` records that the employee
 * (side A) reports to the manager (side B); read back, side A's label is
 * "Reports to" and side B's is "Manages".
 *
 * Example:
 * ```php
 * $relationships = $client->relationships();
 *
 * $rel = $relationships->create($employeeId, $managerId, 'Reports to');
 * $reportsToChain = $relationships->forContact($employeeId);
 * $relationships->terminate($rel->id, new DateTimeImmutable('2026-01-01'));
 * ```
 */
final readonly class RelationshipApi extends AbstractEntityApi
{
    public function __construct(TransportInterface $transport)
    {
        parent::__construct($transport, 'Relationship');
    }

    /**
     * Fetches relationships matching the query, hydrated as {@see Relationship}.
     *
     * Pair it with {@see ofType()} to filter by type and keep refining:
     * ```php
     * $api->get($api->ofType('Reports to')->where('start_date', Operator::GreaterThan, '2025-01-01'));
     * ```
     *
     * @return Result<Relationship>
     */
    public function get(GetQuery $query): Result
    {
        return TypedResult::hydrate($this->executeGet($query), Relationship::class);
    }

    /**
     * Creates a relationship from contact A to contact B.
     *
     * `$type` may be the integer relationship-type id, or the **forward** type name
     * (`nameAToB`, e.g. `'Reports to'`) which is resolved to its id via
     * {@see RelationshipTypeApi::byName()}. Both forms produce an identical final
     * `Relationship.create` request — the string form merely adds one preceding
     * `RelationshipType.get` lookup.
     *
     * `$extra` is merged into the values map for any additional APIv4 fields
     * (e.g. `'description'`, `'is_permission_a_b'`).
     *
     * @param array<string, mixed> $extra
     *
     * Example:
     * ```php
     * $api->create($employeeId, $managerId, 'Reports to', new DateTimeImmutable('2025-01-01'));
     * $api->create($employeeId, $managerId, 7); // same, with the type id directly
     * ```
     */
    public function create(
        int $contactIdA,
        int $contactIdB,
        string|int $type,
        ?DateTimeImmutable $startDate = null,
        array $extra = [],
    ): Relationship {
        $typeId = is_int($type) ? $type : $this->resolveTypeId($type);

        $values = [
            'contact_id_a' => $contactIdA,
            'contact_id_b' => $contactIdB,
            'relationship_type_id' => $typeId,
        ];

        if ($startDate instanceof DateTimeImmutable) {
            $values['start_date'] = $startDate->format('Y-m-d');
        }

        $values = array_merge($values, $extra);

        $created = TypedResult::hydrate(
            $this->executeAction(ActionRequest::create($this->entity, $values)),
            Relationship::class,
        );

        return $created->first() ?? Relationship::fromArray($values);
    }

    /**
     * Ends a relationship: sets its `end_date` and marks it inactive.
     *
     * Example:
     * ```php
     * $api->terminate($relationshipId, new DateTimeImmutable('2026-01-01'));
     * ```
     */
    public function terminate(int $relationshipId, DateTimeImmutable $endDate): void
    {
        $this->executeAction(
            ActionRequest::update(
                $this->entity,
                ['end_date' => $endDate->format('Y-m-d'), 'is_active' => false],
                [['id', '=', $relationshipId]],
            ),
        );
    }

    /**
     * Returns every relationship the contact takes part in, on **either** side.
     *
     * Because a relationship is directional, a contact may be side A in one record
     * and side B in another. The query therefore matches
     * `contact_id_a = X OR contact_id_b = X`, so both directions are returned. The
     * directional labels are joined in so each {@see Relationship} carries
     * `labelAToB` / `labelBToA`.
     *
     * When `$activeOnly` is true (default), only active relationships are returned.
     *
     * @return Result<Relationship>
     *
     * Example:
     * ```php
     * foreach ($api->forContact($employeeId) as $rel) {
     *     // employee may be the "reports to" side (A) or the "manages" side (B)
     * }
     * ```
     */
    public function forContact(int $contactId, bool $activeOnly = true): Result
    {
        $query = GetQuery::new()
            ->select('*', 'relationship_type_id.label_a_b', 'relationship_type_id.label_b_a')
            ->where('contact_id_a', Operator::Equals, $contactId)
            ->orWhere('contact_id_b', Operator::Equals, $contactId);

        if ($activeOnly) {
            $query = $query->where('is_active', Operator::Equals, true);
        }

        return $this->get($query);
    }

    /**
     * Returns a {@see GetQuery} pre-filtered to a relationship type, ready to refine.
     *
     * `$typeName` is resolved through {@see RelationshipTypeApi::byName()} (matching
     * either direction) and pinned as `relationship_type_id = <id>`. The consumer
     * adds further constraints and runs it through {@see get()}:
     *
     * ```php
     * $api->get($api->ofType('Reports to')->where('start_date', Operator::GreaterThan, '2025-01-01'));
     * ```
     */
    public function ofType(string $typeName): GetQuery
    {
        return GetQuery::new()->where('relationship_type_id', Operator::Equals, $this->resolveTypeId($typeName));
    }

    /**
     * Returns the field definitions for the Relationship entity.
     *
     * @return array<mixed>
     */
    #[\Override]
    public function getFields(): array
    {
        return parent::getFields();
    }

    /**
     * Returns the available actions for the Relationship entity.
     *
     * @return array<mixed>
     */
    #[\Override]
    public function getActions(): array
    {
        return parent::getActions();
    }

    /**
     * Resolves relationship type names against a fresh {@see RelationshipTypeApi}
     * sharing this API's transport.
     */
    private function relationshipTypes(): RelationshipTypeApi
    {
        return new RelationshipTypeApi($this->transport);
    }

    /**
     * Resolves a forward/reverse type name to its id, or 0 when unknown.
     */
    private function resolveTypeId(string $typeName): int
    {
        $type = $this->relationshipTypes()->byName($typeName);

        return $type instanceof RelationshipType ? $type->id : 0;
    }
}
