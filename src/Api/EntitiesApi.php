<?php

namespace Woduda\CiviCRM\Api;

use Woduda\CiviCRM\Api\Response\ApiResponse;
use Woduda\CiviCRM\Client;

abstract class EntitiesApi
{
    protected $entity = 'any';

    public function __construct(protected Client $client) {}

    public function get(array $params): ApiResponse
    {
        return $this->client->sendRequest($this->entity . '/get', $params);
    }

    public function create(array $params): ApiResponse
    {
        return $this->client->sendRequest($this->entity . '/create', $params);
    }

    public function update(array $params): ApiResponse
    {
        return $this->client->sendRequest($this->entity . '/update', $params);
    }

    public function save(array $params): ApiResponse
    {
        return $this->client->sendRequest($this->entity . '/save', $params);
    }

    public function delete(array $params): ApiResponse
    {
        return $this->client->sendRequest($this->entity . '/delete', $params);
    }

    public function replace(array $params): ApiResponse
    {
        return $this->client->sendRequest($this->entity . '/replace', $params);
    }
}
