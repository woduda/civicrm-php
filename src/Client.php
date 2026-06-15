<?php

declare(strict_types=1);

namespace Woduda\CiviCRM;

use Http\Discovery\Psr17Factory;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Woduda\CiviCRM\Exception\ApiErrorException;
use Woduda\CiviCRM\Result\ApiResponse;

/**
 * PSR-18 HTTP transport for the CiviCRM APIv4 REST endpoint.
 *
 * Builds and dispatches form-encoded `params` requests, applies the required
 * headers and bearer authentication, and maps responses to {@see ApiResponse}
 * (or throws {@see ApiErrorException} on HTTP 4xx/5xx). The default transport
 * {@see \Woduda\CiviCRM\Http\Transport} delegates to this class; inject your own
 * PSR-18 client here to customise timeouts, retries, or test doubles.
 */
final class Client
{
    /**
     * Headers sent with every request.
     *
     * @var array<string, string>
     */
    private array $defaultHeaders = [
        'X-Requested-With' => 'XMLHttpRequest',
        'Content-Type' => 'application/x-www-form-urlencoded',
    ];

    private readonly ClientInterface $httpClient;

    private readonly Psr17Factory $factory;

    public function __construct(
        private readonly Config $config,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ) {
        $this->httpClient = $httpClient ?? Psr18ClientDiscovery::find();

        $this->factory = new Psr17Factory(
            requestFactory: $requestFactory,
            streamFactory: $streamFactory,
        );
    }

    /**
     * Builds the PSR-7 request for a CiviCRM APIv4 action.
     *
     * @param  array<string, mixed> $params
     * @throws \JsonException
     */
    public function getRequest(string $uri, array $params = []): RequestInterface
    {
        $encoded = json_encode($params, JSON_THROW_ON_ERROR);
        $body = $this->factory->createStream('params=' . urlencode($encoded));

        $request = $this->factory->createRequest('POST', $this->buildUrl($uri))
            ->withBody($body);

        foreach ($this->getAllHeaders() as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }

    /**
     * Sends a request to the API endpoint and wraps the response.
     *
     * @param  array<string, mixed> $params
     * @throws ApiErrorException        On HTTP 4xx/5xx responses
     * @throws ClientExceptionInterface On transport errors
     * @throws \JsonException           On non-encodable parameters
     */
    public function sendRequest(string $uri, array $params = []): ApiResponse
    {
        $request = $this->getRequest($uri, $params);

        $response = $this->httpClient->sendRequest($request);

        if ($response->getStatusCode() >= 400) {
            throw ApiErrorException::fromResponse($response);
        }

        return ApiResponse::fromResponse($response);
    }

    /**
     * Builds the absolute endpoint URL for an entity/action URI.
     */
    private function buildUrl(string $uri): string
    {
        return $this->config->getBaseUrl() . $uri;
    }

    /**
     * Returns all headers sent with a request (defaults + auth).
     *
     * @return array<string, string>
     */
    private function getAllHeaders(): array
    {
        return array_merge($this->defaultHeaders, $this->getAuthHeaders());
    }

    /**
     * Returns the authentication headers.
     *
     * @return array<string, string>
     */
    private function getAuthHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->config->getApiKey()];
    }
}
