<?php

namespace Woduda\CiviCRM;

use Http\Discovery\Psr17Factory;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Woduda\CiviCRM\Api\Exception\ApiException;
use Woduda\CiviCRM\Api\Response\ApiResponse;

final class Client
{
    /**
     * @var array<string, Api\EntitiesApi> 
     **/
    private array $apiCache = [];

    /**
     * @var array<string, string>
     */
    private array $defaultHeaders = [
        'X-Requested-With' => 'XMLHttpRequest',
        'Content-Type' => 'application/x-www-form-urlencoded',
    ];

    /**
     * @var Psr17Factory
     */
    private $factory;

    public function __construct(
        private Config $config,
        private ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null
    ) {
        $this->httpClient = $httpClient ?: Psr18ClientDiscovery::find();

        $this->factory = new Psr17Factory(
            requestFactory: $requestFactory,
            streamFactory: $streamFactory,
        );
    }

    public function activities()
    {
        return $this->apiCache['activities'] ??= new Api\ActivitiesApi($this);
    }

    public function contacts()
    {
        return $this->apiCache['contacts'] ??= new Api\ContactsApi($this);
    }

    public function events()
    {
        return $this->apiCache['events'] ??= new Api\EventsApi($this);
    }

    public function getRequest(string $uri, array $params = []): RequestInterface
    {
        $uri = $this->buildUrl($uri);
        $body = $this->factory->createStream("params=" . urlencode(json_encode($params)));

        $request = $this->factory->createRequest('POST', $uri)
            ->withBody($body);
        foreach ($this->getAllHeaders() as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        return $request;
    }

    public function sendRequest($uri, $params): ApiResponse
    {
        $request = $this->getRequest($uri, $params);

        $response = $this->httpClient->sendRequest($request);

        if ($response->getStatusCode() >= 400) {
            throw ApiException::fromResponse($response);
        }
        return ApiResponse::fromResponse($response);
    }

    protected function buildUrl(string $uri): string
    {
        return $this->config->getBaseUrl() . $uri;
    }

    protected function getAllHeaders(): array
    {
        return array_merge(
            $this->defaultHeaders,
            $this->getAuthHeaders()
        );
    }

    protected function getAuthHeaders()
    {
        return ['Authorization' => 'Bearer ' . $this->config->getApiKey()];
    }
}
