<?php

namespace Woduda\CiviCRM;

class Config
{
    /**
     * @param string $baseUrl
     *  Base url to CRM API instance in form: 
     *  https://yourcivicrm.org/civicrm/ajax/api4/
     * @param string $apiKey
     *  Secret API key from CiviCRM
     * @param array $headers
     *  Additional headers to send in every request
     */
    public function __construct(
        private string $baseUrl,
        private string $apiKey,
        private array $headers = []
    ) {}

    /**
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * @return string
     */
    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}
