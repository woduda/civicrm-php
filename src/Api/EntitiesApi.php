<?php

namespace Woduda\CiviCRM\Api;

use Woduda\CiviCRM\Api\Response\ApiResponse;
use Woduda\CiviCRM\Client;

abstract class EntitiesApi
{
    /**
     * Entity name to use in request uri
     *
     * @var string
     */
    protected $entity = 'any';

    /**
     * @param Client $client
     */
    public function __construct(protected Client $client) {}

    /**
     * Execute "get" request
     *
     * @param array $params
     * @return ApiResponse
     */
    public function get(array $params): ApiResponse
    {
        return $this->client->sendRequest($this->entity . '/get', $params);
    }

    /**
     * Execute "create" request
     *
     * @param array $params
     * @return ApiResponse
     */
    public function create(array $params): ApiResponse
    {
        return $this->client->sendRequest($this->entity . '/create', $params);
    }

    /**
     * Execute "update" request
     *
     * @param array $params
     * @return ApiResponse
     */
    public function update(array $params): ApiResponse
    {
        return $this->client->sendRequest($this->entity . '/update', $params);
    }

    /**
     * Execute "save" request
     *
     * @param array $params
     * @return ApiResponse
     */
    public function save(array $params): ApiResponse
    {
        return $this->client->sendRequest($this->entity . '/save', $params);
    }

    /**
     * Execute "delete" request
     *
     * @param array $params
     * @return ApiResponse
     */
    public function delete(array $params): ApiResponse
    {
        return $this->client->sendRequest($this->entity . '/delete', $params);
    }

    /**
     * Execute "replace" request
     *
     * @param array $params
     * @return ApiResponse
     */
    public function replace(array $params): ApiResponse
    {
        return $this->client->sendRequest($this->entity . '/replace', $params);
    }

    /**
     * Execute "getactions" request
     *
     * @param array $params
     * @return ApiResponse
     */
    public function getActions(array $params = []): ApiResponse
    {
        return $this->client->sendRequest($this->entity . '/getactions', $params);
    }

    /**
     * Execute "getfields" request
     *
     * @param array $params
     * @return ApiResponse
     */
    public function getFields(array $params = []): ApiResponse
    {
        return $this->client->sendRequest($this->entity . '/getfields', $params);
    }
}
