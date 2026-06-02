<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Query;

/**
 * Immutable representation of a CiviCRM APIv4 write action
 * (`create` / `update` / `save` / `delete`).
 *
 * Build it with a named constructor and call {@see toParams()} for the APIv4
 * `params` array. With-er methods return new instances.
 *
 * Example:
 * ```php
 * $params = ActionRequest::create('Contact', ['first_name' => 'Jane'])
 *     ->withChain('email', ActionRequest::create('Email', ['email' => 'jane@example.org', 'contact_id' => '$id']))
 *     ->toParams();
 * ```
 */
final readonly class ActionRequest
{
    /**
     * @param array<array-key, mixed>    $values A values map (create/update) or a list of records (save)
     * @param array<array-key, mixed>    $where  APIv4 where clauses (update/delete)
     * @param array<string, list<mixed>> $chain  APIv4 chain map
     */
    private function __construct(
        public string $entity,
        public string $action,
        public array $values = [],
        public array $where = [],
        public ?int $limit = null,
        public array $chain = [],
    ) {}

    /**
     * Builds a `create` action.
     *
     * Example:
     * ```php
     * ActionRequest::create('Contact', ['contact_type' => 'Individual', 'first_name' => 'Jane']);
     * ```
     *
     * @param array<string, mixed> $values
     */
    public static function create(string $entity, array $values): self
    {
        return new self($entity, 'create', $values);
    }

    /**
     * Builds an `update` action.
     *
     * Example:
     * ```php
     * ActionRequest::update('Contact', ['first_name' => 'Jane'], [['id', '=', 42]]);
     * ```
     *
     * @param array<string, mixed>    $values
     * @param array<array-key, mixed> $where
     */
    public static function update(string $entity, array $values, array $where = []): self
    {
        return new self($entity, 'update', $values, $where);
    }

    /**
     * Builds a `save` action (bulk upsert of records).
     *
     * Example:
     * ```php
     * ActionRequest::save('Contact', [['first_name' => 'A'], ['first_name' => 'B']]);
     * ```
     *
     * @param list<array<string, mixed>> $records
     */
    public static function save(string $entity, array $records): self
    {
        return new self($entity, 'save', $records);
    }

    /**
     * Builds a `delete` action.
     *
     * Example:
     * ```php
     * ActionRequest::delete('Contact', [['id', '=', 42]]);
     * ```
     *
     * @param array<array-key, mixed> $where
     */
    public static function delete(string $entity, array $where): self
    {
        return new self($entity, 'delete', [], $where);
    }

    /**
     * Returns a copy with a record limit applied.
     *
     * Example:
     * ```php
     * ActionRequest::delete('Contact', [['is_deleted', '=', 1]])->withLimit(100);
     * ```
     */
    public function withLimit(int $n): self
    {
        return new self($this->entity, $this->action, $this->values, $this->where, $n, $this->chain);
    }

    /**
     * Returns a copy with a chained sub-call added under `$name`.
     *
     * An {@see ActionRequest} sub uses its own entity/action; a {@see GetQuery}
     * sub is chained as a `get` on this request's entity.
     *
     * Example:
     * ```php
     * ActionRequest::create('Contact', ['first_name' => 'Jane'])
     *     ->withChain('email', ActionRequest::create('Email', ['email' => 'jane@example.org', 'contact_id' => '$id']));
     * ```
     */
    public function withChain(string $name, ActionRequest|GetQuery $sub): self
    {
        $entry = $sub instanceof self
            ? [$sub->entity, $sub->action, $sub->toParams()]
            : [$this->entity, 'get', $sub->toParams()];

        return new self(
            $this->entity,
            $this->action,
            $this->values,
            $this->where,
            $this->limit,
            [...$this->chain, $name => $entry],
        );
    }

    /**
     * Returns a copy with every entry of a {@see ChainBuilder} merged in.
     *
     * Example:
     * ```php
     * ActionRequest::create('Contact', ['first_name' => 'Jane'])
     *     ->withChainBuilder(ChainBuilder::new()->create('email', 'Email', ['email' => 'jane@example.org']));
     * ```
     */
    public function withChainBuilder(ChainBuilder $builder): self
    {
        return new self(
            $this->entity,
            $this->action,
            $this->values,
            $this->where,
            $this->limit,
            [...$this->chain, ...$builder->toParams()],
        );
    }

    /**
     * Produces the APIv4 `params` array for this action.
     *
     * Example:
     * ```php
     * ActionRequest::update('Contact', ['first_name' => 'Jane'], [['id', '=', 1]])->toParams();
     * // ['values' => ['first_name' => 'Jane'], 'where' => [['id', '=', 1]]]
     * ```
     *
     * @return array<string, mixed>
     */
    public function toParams(): array
    {
        $params = match ($this->action) {
            'create' => ['values' => $this->values],
            'update' => ['values' => $this->values, 'where' => $this->where],
            'save' => ['records' => $this->values],
            'delete' => ['where' => $this->where],
            default => [],
        };

        if ($this->limit !== null) {
            $params['limit'] = $this->limit;
        }

        if ($this->chain !== []) {
            $params['chain'] = $this->chain;
        }

        return $params;
    }
}
