<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Query;

/**
 * Immutable builder for the APIv4 `chain` structure.
 *
 * A chain runs follow-up API calls for each result of the primary call; sub-calls
 * may reference the parent record's fields via `$id`, `$xxx` placeholders. Each
 * entry is `[Entity, Action, params, index?]` keyed by an alias.
 *
 * Example:
 * ```php
 * $chain = ChainBuilder::new()
 *     ->create('email', 'Email', ['email' => 'jane@example.org', 'contact_id' => '$id'])
 *     ->toParams();
 * // ['email' => ['Email', 'create', ['values' => ['email' => 'jane@example.org', 'contact_id' => '$id']]]]
 * ```
 */
final readonly class ChainBuilder
{
    /**
     * @param array<string, list<mixed>> $chains
     */
    private function __construct(public array $chains = []) {}

    /**
     * Starts a new, empty chain.
     *
     * Example:
     * ```php
     * $chain = ChainBuilder::new();
     * ```
     */
    public static function new(): self
    {
        return new self();
    }

    /**
     * Adds a chained call. `$params` may be raw APIv4 params or a {@see GetQuery}.
     *
     * Example:
     * ```php
     * ChainBuilder::new()->add('tags', 'EntityTag', 'create', ['values' => ['tag_id' => 3]]);
     * ```
     *
     * @param GetQuery|array<array-key, mixed> $params
     */
    public function add(string $name, string $entity, string $action, GetQuery|array $params = [], ?string $index = null): self
    {
        $resolved = $params instanceof GetQuery ? $params->toParams() : $params;

        $entry = $index === null
            ? [$entity, $action, $resolved]
            : [$entity, $action, $resolved, $index];

        return new self([...$this->chains, $name => $entry]);
    }

    /**
     * Adds a chained `create` call.
     *
     * Example:
     * ```php
     * ChainBuilder::new()->create('email', 'Email', ['email' => 'jane@example.org', 'contact_id' => '$id']);
     * ```
     *
     * @param array<string, mixed> $values
     */
    public function create(string $name, string $entity, array $values, ?string $index = null): self
    {
        return $this->add($name, $entity, 'create', ['values' => $values], $index);
    }

    /**
     * Adds a chained `get` call.
     *
     * Example:
     * ```php
     * ChainBuilder::new()->get('emails', 'Email', GetQuery::new()->where('contact_id', Operator::Equals, '$id'));
     * ```
     */
    public function get(string $name, string $entity, GetQuery $query, ?string $index = null): self
    {
        return $this->add($name, $entity, 'get', $query, $index);
    }

    /**
     * Returns the APIv4 `chain` map.
     *
     * Example:
     * ```php
     * ChainBuilder::new()->create('email', 'Email', ['email' => 'jane@example.org'])->toParams();
     * ```
     *
     * @return array<string, list<mixed>>
     */
    public function toParams(): array
    {
        return $this->chains;
    }
}
