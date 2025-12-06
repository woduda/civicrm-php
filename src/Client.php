<?php

namespace Woduda\CiviCRM;

use Http\Discovery\Psr17Factory;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class Client
{
    /**
     * @var array<string, Api\ApiInterface> 
     **/
    private array $apiCache = [];

    /**
     * @var array<string, string>
     */
    private array $defaultHeaders = [
        'X-Requested-With' => 'XMLHttpRequest',
        'Content-Type' => 'application/json',
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
        $uri = $this->getUrl($uri) . '?params=' . urlencode(json_encode($params));

        $request = $this->factory->createRequest('GET', $uri);
        foreach ($this->getAllHeaders() as $name => $value) {
            $request->withHeader($name, $value);
        }
        return $request;
    }

    public function sendRequest($uri, $params): ResponseInterface
    {
        $request = $this->getRequest($uri, $params);

        return $this->httpClient->sendRequest($request);
    }

    protected function getUrl(string $uri): string
    {
        return $this->config->getBaseUrl() + $uri;
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
