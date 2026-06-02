<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Query;

use Woduda\CiviCRM\Exception\ValidationException;

/**
 * Immutable builder for a CiviCRM APIv4 `get` request.
 *
 * Every method returns a new instance (with-er pattern); the original is never
 * mutated. Call {@see toParams()} to obtain the APIv4 `params` array.
 *
 * Example:
 * ```php
 * $params = GetQuery::new()
 *     ->select('id', 'display_name')
 *     ->where('contact_type', Operator::Equals, 'Individual')
 *     ->orderBy('display_name')
 *     ->limit(25)
 *     ->toParams();
 * ```
 */
final readonly class GetQuery
{
    /**
     * @param list<string>                                                  $select
     * @param list<array{conjunction: Conjunction, clause: list<mixed>}>    $where
     * @param array<string, 'ASC'|'DESC'>                                   $orderBy
     * @param list<string>                                                  $groupBy
     * @param list<list<mixed>>                                             $having
     */
    private function __construct(
        public array $select = [],
        public array $where = [],
        public array $orderBy = [],
        public ?int $limit = null,
        public int $offset = 0,
        public array $groupBy = [],
        public array $having = [],
    ) {}

    /**
     * Starts a new, empty query.
     *
     * Example:
     * ```php
     * $query = GetQuery::new();
     * ```
     */
    public static function new(): self
    {
        return new self();
    }

    /**
     * Replaces the selected fields.
     *
     * Example:
     * ```php
     * GetQuery::new()->select('id', 'email');
     * ```
     */
    public function select(string ...$fields): self
    {
        return $this->with(select: array_values($fields));
    }

    /**
     * Appends to the selected fields.
     *
     * Example:
     * ```php
     * GetQuery::new()->select('id')->addSelect('email', 'phone');
     * ```
     */
    public function addSelect(string ...$fields): self
    {
        return $this->with(select: array_values([...$this->select, ...$fields]));
    }

    /**
     * Adds an `AND` condition.
     *
     * Example:
     * ```php
     * GetQuery::new()->where('first_name', Operator::Equals, 'Jane');
     * ```
     */
    public function where(string $field, Operator $op, mixed $value = null): self
    {
        return $this->appendClause(Conjunction::And_, $field, $op, $value);
    }

    /**
     * Adds an `OR` condition, grouped with the preceding clause.
     *
     * Example:
     * ```php
     * GetQuery::new()
     *     ->where('first_name', Operator::Equals, 'Jane')
     *     ->orWhere('first_name', Operator::Equals, 'John');
     * // where => [['OR', [['first_name','=','Jane'], ['first_name','=','John']]]]
     * ```
     */
    public function orWhere(string $field, Operator $op, mixed $value = null): self
    {
        return $this->appendClause(Conjunction::Or_, $field, $op, $value);
    }

    /**
     * Adds an `AND` `IN` condition.
     *
     * Example:
     * ```php
     * GetQuery::new()->whereIn('id', [1, 2, 3]);
     * ```
     *
     * @param list<mixed> $values
     */
    public function whereIn(string $field, array $values): self
    {
        return $this->appendClause(Conjunction::And_, $field, Operator::In, $values);
    }

    /**
     * Adds an `AND` `IS NULL` condition.
     *
     * Example:
     * ```php
     * GetQuery::new()->whereNull('deleted_date');
     * ```
     */
    public function whereNull(string $field): self
    {
        return $this->appendClause(Conjunction::And_, $field, Operator::IsNull, null);
    }

    /**
     * Adds an order-by clause. Direction must be `ASC` or `DESC`.
     *
     * Example:
     * ```php
     * GetQuery::new()->orderBy('created_date', 'DESC');
     * ```
     *
     * @throws ValidationException When the direction is not ASC/DESC.
     */
    public function orderBy(string $field, string $direction = 'ASC'): self
    {
        $normalized = strtoupper($direction);

        if ($normalized !== 'ASC' && $normalized !== 'DESC') {
            throw ValidationException::invalidOrderDirection($direction);
        }

        return $this->with(orderBy: [...$this->orderBy, $field => $normalized]);
    }

    /**
     * Sets the maximum number of records to return.
     *
     * Example:
     * ```php
     * GetQuery::new()->limit(50);
     * ```
     */
    public function limit(int $n): self
    {
        return $this->with(limit: $n);
    }

    /**
     * Sets the number of records to skip.
     *
     * Example:
     * ```php
     * GetQuery::new()->offset(100);
     * ```
     */
    public function offset(int $n): self
    {
        return $this->with(offset: $n);
    }

    /**
     * Replaces the group-by fields.
     *
     * Example:
     * ```php
     * GetQuery::new()->groupBy('contact_type');
     * ```
     */
    public function groupBy(string ...$fields): self
    {
        return $this->with(groupBy: array_values($fields));
    }

    /**
     * Adds an `AND` `having` condition (applied after grouping).
     *
     * Example:
     * ```php
     * GetQuery::new()->groupBy('contact_type')->having('row_count', Operator::GreaterThan, 5);
     * ```
     */
    public function having(string $field, Operator $op, mixed $value = null): self
    {
        $clause = $op->requiresValue() ? [$field, $op->value, $value] : [$field, $op->value];

        return $this->with(having: [...$this->having, $clause]);
    }

    /**
     * Produces the APIv4 `params` array, omitting empty/default keys.
     *
     * Example:
     * ```php
     * GetQuery::new()->select('id')->limit(10)->toParams();
     * // ['select' => ['id'], 'limit' => 10]
     * ```
     *
     * @return array<string, mixed>
     */
    public function toParams(): array
    {
        $params = [];

        if ($this->select !== []) {
            $params['select'] = $this->select;
        }

        $where = $this->buildWhere();
        if ($where !== []) {
            $params['where'] = $where;
        }

        if ($this->orderBy !== []) {
            $params['orderBy'] = $this->orderBy;
        }

        if ($this->limit !== null) {
            $params['limit'] = $this->limit;
        }

        if ($this->offset !== 0) {
            $params['offset'] = $this->offset;
        }

        if ($this->groupBy !== []) {
            $params['groupBy'] = $this->groupBy;
        }

        if ($this->having !== []) {
            $params['having'] = $this->having;
        }

        return $params;
    }

    /**
     * Appends a where clause tagged with its conjunction.
     */
    private function appendClause(Conjunction $conjunction, string $field, Operator $op, mixed $value): self
    {
        $clause = $op->requiresValue() ? [$field, $op->value, $value] : [$field, $op->value];

        return $this->with(where: [...$this->where, ['conjunction' => $conjunction, 'clause' => $clause]]);
    }

    /**
     * Folds the tagged clause list into the APIv4 `where` tree (Laravel-style).
     *
     * @return list<mixed>
     */
    private function buildWhere(): array
    {
        $result = [];

        foreach ($this->where as $entry) {
            $leaf = $entry['clause'];

            if ($entry['conjunction'] === Conjunction::And_ || $result === []) {
                $result[] = $leaf;

                continue;
            }

            $lastIndex = count($result) - 1;
            $last = $result[$lastIndex];

            if (($last[0] ?? null) === 'OR' && isset($last[1]) && is_array($last[1])) {
                $result[$lastIndex] = ['OR', [...$last[1], $leaf]];

                continue;
            }

            $result[$lastIndex] = ['OR', [$last, $leaf]];
        }

        return $result;
    }

    /**
     * Returns a copy with the given fields overridden.
     *
     * @param list<string>|null                                                 $select
     * @param list<array{conjunction: Conjunction, clause: list<mixed>}>|null   $where
     * @param array<string, 'ASC'|'DESC'>|null                                  $orderBy
     * @param list<string>|null                                                 $groupBy
     * @param list<list<mixed>>|null                                            $having
     */
    private function with(
        ?array $select = null,
        ?array $where = null,
        ?array $orderBy = null,
        ?int $limit = null,
        ?int $offset = null,
        ?array $groupBy = null,
        ?array $having = null,
    ): self {
        return new self(
            select: $select ?? $this->select,
            where: $where ?? $this->where,
            orderBy: $orderBy ?? $this->orderBy,
            limit: $limit ?? $this->limit,
            offset: $offset ?? $this->offset,
            groupBy: $groupBy ?? $this->groupBy,
            having: $having ?? $this->having,
        );
    }
}
