<?php

namespace Woduda\CiviCRM;

use Http\Discovery\Psr17Factory;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Woduda\CiviCRM\Api\{
    ActivitiesApi,
    AddressesApi,
    ContactsApi,
    ContributionsApi,
    EmailsApi,
    EventsApi,
    ParticipantsApi,
    PhonesApi
};
use Woduda\CiviCRM\Api\Exception\ApiException;
use Woduda\CiviCRM\Api\Response\ApiResponse;

final class Client
{
    /**
     * @var array<string, Api\EntitiesApi> 
     */
    private array $apiCache = [];

    /**
     * Default headers to send in every request
     * 
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

    /**
     * @param Config $config
     *  Config instance.
     * @param ClientInterface|null $httpClient
     *  PSR-18 compliant HTTP client.
     * @param RequestFactoryInterface|null $requestFactory
     *  Optional request factory instance.
     * @param StreamFactoryInterface|null $streamFactory
     *  Optional stream factory instance.
     */
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

    /**
     * @return ActivitiesApi
     */
    public function activities(): ActivitiesApi
    {
        return $this->apiCache['activities'] ??= new ActivitiesApi($this);
    }

    /**
     * @return AddressesApi
     */
    public function addresses(): AddressesApi
    {
        return $this->apiCache['addresses'] ??= new AddressesApi($this);
    }

    /**
     * @return ContactsApi
     */
    public function contacts(): ContactsApi
    {
        return $this->apiCache['contacts'] ??= new ContactsApi($this);
    }

    /**
     * @return ContributionsApi
     */
    public function contributions(): ContributionsApi
    {
        return $this->apiCache['contributions'] ??= new ContributionsApi($this);
    }

    /**
     * @return EmailsApi
     */
    public function emails(): EmailsApi
    {
        return $this->apiCache['emails'] ??= new EmailsApi($this);
    }

    /**
     * @return EventsApi
     */
    public function events(): EventsApi
    {
        return $this->apiCache['events'] ??= new EventsApi($this);
    }

    /**
     * @return ParticipantsApi
     */
    public function participants(): ParticipantsApi
    {
        return $this->apiCache['participants'] ??= new ParticipantsApi($this);
    }

    /**
     * @return PhonesApi
     */
    public function phones(): PhonesApi
    {
        return $this->apiCache['phones'] ??= new PhonesApi($this);
    }

    /**
     * Creates Request instance
     *
     * @param string $uri
     * @param array $params
     * @return RequestInterface
     */
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

    /**
     * Sends request to API endpoint with HTTP client
     *
     * @param string $uri
     * @param array $params
     * @return ApiResponse
     * @throws ApiException
     * @throws ClientExceptionInterface
     */
    public function sendRequest(string $uri, array $params = []): ApiResponse
    {
        $request = $this->getRequest($uri, $params);

        $response = $this->httpClient->sendRequest($request);

        if ($response->getStatusCode() >= 400) {
            throw ApiException::fromResponse($response);
        }
        return ApiResponse::fromResponse($response);
    }

    /**
     * Builds API endpoint url
     *
     * @param string $uri
     * @return string
     */
    protected function buildUrl(string $uri): string
    {
        return $this->config->getBaseUrl() . $uri;
    }

    /**
     * Returns all headers to send in request
     *
     * @return array
     */
    protected function getAllHeaders(): array
    {
        return array_merge(
            $this->defaultHeaders,
            $this->getAuthHeaders()
        );
    }

    /**
     * Returns Authentication headers
     *
     * @return array
     */
    protected function getAuthHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->config->getApiKey()];
    }
}
