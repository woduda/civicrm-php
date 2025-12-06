<?php

namespace Woduda\CiviCRM;

class Config
{

    public function __construct(
        private string $baseUrl,
        private string $apiKey,
        private array $headers = []
    ) {}

    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

    public function getApiKey()
    {
        return $this->apiKey;
    }

    public function getHeaders()
    {
        return $this->headers;
    }
}
