<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Api;

use Woduda\CiviCRM\Client;
use Woduda\CiviCRM\Result\ApiResponse;

/**
 * Base class for CiviCRM APIv4 entity accessors.
 *
 * Concrete subclasses only declare the {@see $entity} name; every standard
 * APIv4 action is provided here as a thin wrapper over the transport.
 */
abstract class EntitiesApi
{
    /**
     * CiviCRM entity name used in the request URI.
     */
    protected string $entity = 'any';

    public function __construct(protected readonly Client $client) {}

    /**
     * @param array<string, mixed> $params
     */
    public function get(array $params = []): ApiResponse
    {
        return $this->client->sendRequest($this->entity . '/get', $params);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function create(array $params = []): ApiResponse
    {
        return $this->client->sendRequest($this->entity . '/create', $params);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function update(array $params = []): ApiResponse
    {
        return $this->client->sendRequest($this->entity . '/update', $params);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function save(array $params = []): ApiResponse
    {
        return $this->client->sendRequest($this->entity . '/save', $params);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function delete(array $params = []): ApiResponse
    {
        return $this->client->sendRequest($this->entity . '/delete', $params);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function replace(array $params = []): ApiResponse
    {
        return $this->client->sendRequest($this->entity . '/replace', $params);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function getActions(array $params = []): ApiResponse
    {
        return $this->client->sendRequest($this->entity . '/getactions', $params);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function getFields(array $params = []): ApiResponse
    {
        return $this->client->sendRequest($this->entity . '/getfields', $params);
    }
}
