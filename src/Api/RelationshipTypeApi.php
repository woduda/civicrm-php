<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Api;

use Woduda\CiviCRM\Contract\TransportInterface;
use Woduda\CiviCRM\Entity\RelationshipType;
use Woduda\CiviCRM\Query\ActionRequest;
use Woduda\CiviCRM\Query\GetQuery;
use Woduda\CiviCRM\Query\Operator;
use Woduda\CiviCRM\Result\Result;
use Woduda\CiviCRM\Result\TypedResult;

/**
 * Typed API for the CiviCRM `RelationshipType` entity.
 *
 * Relationship types are bidirectional: each one has a forward name/label
 * (`nameAToB`/`labelAToB`) and a reverse name/label (`nameBToA`/`labelBToA`). See
 * {@see RelationshipType} for the "Reports to" / "Manages" walkthrough.
 *
 * Because types change very rarely, {@see all()} is memoized in memory for the
 * lifetime of the instance — reuse the same API object to benefit from the cache.
 *
 * Example:
 * ```php
 * $types = $client->relationshipTypes();
 *
 * $type = $types->ensureExists('Reports to', 'Manages', 'Reports to', 'Manages');
 * $employee = $types->byName('Manages'); // same record, matched on the reverse name
 * ```
 */
final readonly class RelationshipTypeApi extends AbstractEntityApi
{
    private RelationshipTypeCache $cache;

    public function __construct(TransportInterface $transport)
    {
        parent::__construct($transport, 'RelationshipType');
        $this->cache = new RelationshipTypeCache();
    }

    /**
     * Returns every relationship type, memoized in memory after the first call.
     *
     * The first call hits the transport; subsequent calls return the cached
     * {@see Result} without dispatching another request. {@see ensureExists()}
     * invalidates the cache when it creates a new type.
     *
     * @return Result<RelationshipType>
     *
     * Example:
     * ```php
     * foreach ($api->all() as $type) {
     *     echo $type->labelAToB, ' / ', $type->labelBToA, PHP_EOL;
     * }
     * ```
     */
    public function all(): Result
    {
        $cached = $this->cache->get();

        if ($cached instanceof Result) {
            return $cached;
        }

        $types = TypedResult::hydrate($this->executeGet(GetQuery::new()), RelationshipType::class);
        $this->cache->set($types);

        return $types;
    }

    /**
     * Finds a relationship type by either of its directional names.
     *
     * `$name` is matched against **both** `nameAToB` and `nameBToA`, so the two
     * directions of the same type resolve to the same record — e.g. both
     * `'Employee of'` and `'Employer of'` return the employer/employee type.
     *
     * Returns `null` when no type matches.
     *
     * Example:
     * ```php
     * $api->byName('Employee of'); // same record as...
     * $api->byName('Employer of'); // ...this call
     * ```
     */
    public function byName(string $name): ?RelationshipType
    {
        foreach ($this->all() as $type) {
            if ($type->nameAToB === $name || $type->nameBToA === $name) {
                return $type;
            }
        }

        return null;
    }

    /**
     * Returns the relationship type matching `$nameAToB`, creating it if absent.
     *
     * Idempotent get-or-create keyed on the forward name `name_a_b`: calling it
     * twice with the same forward name does not create a duplicate. This makes it
     * the building block for an idempotent schema seeder. Creating a new type
     * invalidates the {@see all()} cache.
     *
     * `$contactTypeA` / `$contactTypeB` optionally restrict each side to a contact
     * type (`'Individual'` / `'Organization'` / `'Household'`); `null` leaves the
     * side unrestricted.
     *
     * Example:
     * ```php
     * $type = $api->ensureExists('Reports to', 'Manages', 'Reports to', 'Manages');
     * ```
     */
    public function ensureExists(
        string $nameAToB,
        string $nameBToA,
        string $labelAToB,
        string $labelBToA,
        ?string $contactTypeA = null,
        ?string $contactTypeB = null,
    ): RelationshipType {
        $existing = $this->executeGet(
            GetQuery::new()
                ->where('name_a_b', Operator::Equals, $nameAToB)
                ->limit(1),
        );

        $first = $existing->first();

        if (is_array($first)) {
            return RelationshipType::fromArray($first);
        }

        $values = [
            'name_a_b' => $nameAToB,
            'name_b_a' => $nameBToA,
            'label_a_b' => $labelAToB,
            'label_b_a' => $labelBToA,
        ];

        if ($contactTypeA !== null) {
            $values['contact_type_a'] = $contactTypeA;
        }

        if ($contactTypeB !== null) {
            $values['contact_type_b'] = $contactTypeB;
        }

        $created = TypedResult::hydrate(
            $this->executeAction(ActionRequest::create($this->entity, $values)),
            RelationshipType::class,
        );

        $this->cache->clear();

        return $created->first() ?? RelationshipType::fromArray($values);
    }

    /**
     * Returns the field definitions for the RelationshipType entity.
     *
     * @return array<mixed>
     */
    #[\Override]
    public function getFields(): array
    {
        return parent::getFields();
    }

    /**
     * Returns the available actions for the RelationshipType entity.
     *
     * @return array<mixed>
     */
    #[\Override]
    public function getActions(): array
    {
        return parent::getActions();
    }
}
