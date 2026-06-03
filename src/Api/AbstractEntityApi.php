<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Api;

use Woduda\CiviCRM\Contract\TransportInterface;
use Woduda\CiviCRM\Query\ActionRequest;
use Woduda\CiviCRM\Query\GetQuery;

/**
 * Base class for all CiviCRM APIv4 entity API classes.
 *
 * Concrete subclasses receive a {@see TransportInterface} and an entity name;
 * the four protected helpers map query/action objects to transport calls.
 *
 * TODO PR#5: executeGet and executeAction should return a typed Result object
 *            once the Result value object is introduced.
 */
abstract readonly class AbstractEntityApi
{
    public function __construct(
        protected TransportInterface $transport,
        protected string $entity,
    ) {}

    /**
     * Executes a `get` query and returns the raw values array.
     *
     * @return array<mixed>
     * @TODO PR#5: change return type to Result once it is introduced
     */
    protected function executeGet(GetQuery $query): array
    {
        return $this->transport->send($this->entity, 'get', $query->toParams())->values;
    }

    /**
     * Executes a write action and returns the raw values array.
     *
     * @return array<mixed>
     * @TODO PR#5: change return type to Result once it is introduced
     */
    protected function executeAction(ActionRequest $request): array
    {
        return $this->transport->send($this->entity, $request->action, $request->toParams())->values;
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
