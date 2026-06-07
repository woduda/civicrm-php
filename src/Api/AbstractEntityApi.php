<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Api;

use Woduda\CiviCRM\Contract\TransportInterface;
use Woduda\CiviCRM\Query\ActionRequest;
use Woduda\CiviCRM\Query\GetQuery;
use Woduda\CiviCRM\Result\Result;

/**
 * Base class for all CiviCRM APIv4 entity API classes.
 *
 * Concrete subclasses receive a {@see TransportInterface} and an entity name;
 * the four protected helpers map query/action objects to transport calls.
 */
abstract readonly class AbstractEntityApi
{
    public function __construct(
        protected TransportInterface $transport,
        protected string $entity,
    ) {}

    /**
     * Executes a `get` query and returns the response as a {@see Result}.
     *
     * @return Result<array<string, mixed>>
     */
    protected function executeGet(GetQuery $query): Result
    {
        return Result::fromApiResponse(
            $this->transport->send($this->entity, 'get', $query->toParams()),
        );
    }

    /**
     * Executes a write action and returns the response as a {@see Result}.
     *
     * @return Result<array<string, mixed>>
     */
    protected function executeAction(ActionRequest $request): Result
    {
        return Result::fromApiResponse(
            $this->transport->send($this->entity, $request->action, $request->toParams()),
        );
    }

    /**
     * Returns the field definitions for this entity.
     *
     * @return array<mixed>
     */
    protected function getFields(): array
    {
        return $this->transport->send($this->entity, 'getfields', [])->values;
    }

    /**
     * Returns the available actions for this entity.
     *
     * @return array<mixed>
     */
    protected function getActions(): array
    {
        return $this->transport->send($this->entity, 'getactions', [])->values;
    }
}
