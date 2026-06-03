<?php

declare(strict_types=1);

namespace Woduda\CiviCRM;

use Psr\Http\Client\ClientExceptionInterface;
use Woduda\CiviCRM\Api\GenericApi;
use Woduda\CiviCRM\Contract\TransportInterface;
use Woduda\CiviCRM\Exception\ApiException;
use Woduda\CiviCRM\Http\Transport;

/**
 * Primary entry point for the CiviCRM APIv4 client.
 *
 * Example:
 * ```php
 * $client = CiviCrmClient::create(new Config($baseUrl, $apiKey));
 *
 * $contacts = $client->contacts()->get(GetQuery::new()->limit(10));
 * $client->entity('Tag')->create(['name' => 'VIP']);
 * ```
 */
final readonly class CiviCrmClient
{
    public function __construct(private TransportInterface $transport) {}

    /**
     * Creates a client with a default PSR-18 transport (auto-discovered HTTP client).
     */
    public static function create(Config $config): self
    {
        return new self(Transport::createDefault($config));
    }

    /**
     * Returns a {@see GenericApi} accessor for any CiviCRM entity by name.
     */
    public function entity(string $name): GenericApi
    {
        return new GenericApi($this->transport, $name);
    }

    /**
     * Returns a GenericApi for the Contact entity.
     *
     * @TODO PR#4: replace with a typed ContactsApi
     */
    public function contacts(): GenericApi
    {
        return $this->entity('Contact');
    }

    /**
     * Returns a GenericApi for the Activity entity.
     *
     * @TODO PR#4: replace with a typed ActivitiesApi
     */
    public function activities(): GenericApi
    {
        return $this->entity('Activity');
    }

    /**
     * Returns a GenericApi for the Tag entity.
     *
     * @TODO PR#4: replace with a typed TagsApi
     */
    public function tags(): GenericApi
    {
        return $this->entity('Tag');
    }

    /**
     * Returns a GenericApi for the Group entity.
     *
     * @TODO PR#4: replace with a typed GroupsApi
     */
    public function groups(): GenericApi
    {
        return $this->entity('Group');
    }

    /**
     * Sends a raw APIv4 request and returns the response values array.
     *
     * This is an escape hatch for actions not covered by typed methods.
     *
     * @param  array<string, mixed> $params
     * @return array<mixed>
     * @throws ApiException             On HTTP 4xx/5xx responses
     * @throws ClientExceptionInterface On transport-level errors
     */
    public function raw(string $entity, string $action, array $params = []): array
    {
        return $this->transport->send($entity, $action, $params)->values;
    }
}
