<?php

declare(strict_types=1);

namespace Woduda\CiviCRM;

use Psr\Http\Client\ClientExceptionInterface;
use Woduda\CiviCRM\Api\ActivityApi;
use Woduda\CiviCRM\Api\AddressApi;
use Woduda\CiviCRM\Api\ContactApi;
use Woduda\CiviCRM\Api\CustomFieldResolver;
use Woduda\CiviCRM\Api\EmailApi;
use Woduda\CiviCRM\Api\GenericApi;
use Woduda\CiviCRM\Api\GroupApi;
use Woduda\CiviCRM\Api\PhoneApi;
use Woduda\CiviCRM\Api\RelationshipApi;
use Woduda\CiviCRM\Api\RelationshipTypeApi;
use Woduda\CiviCRM\Api\TagApi;
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
     * Returns a typed {@see ContactApi} for the Contact entity.
     */
    public function contacts(): ContactApi
    {
        return new ContactApi($this->transport, new CustomFieldResolver($this->transport));
    }

    /**
     * Returns a typed {@see ActivityApi} for the Activity entity.
     */
    public function activities(): ActivityApi
    {
        return new ActivityApi($this->transport);
    }

    /**
     * Returns a typed {@see TagApi} for the Tag entity.
     */
    public function tags(): TagApi
    {
        return new TagApi($this->transport);
    }

    /**
     * Returns a typed {@see GroupApi} for the Group entity.
     */
    public function groups(): GroupApi
    {
        return new GroupApi($this->transport);
    }

    // TODO(PR#13): add emails()/phones()/addresses() to ClientInterface when it lands.

    /**
     * Returns a typed {@see EmailApi} for the Email entity.
     */
    public function emails(): EmailApi
    {
        return new EmailApi($this->transport);
    }

    /**
     * Returns a typed {@see PhoneApi} for the Phone entity.
     */
    public function phones(): PhoneApi
    {
        return new PhoneApi($this->transport);
    }

    /**
     * Returns a typed {@see AddressApi} for the Address entity.
     */
    public function addresses(): AddressApi
    {
        return new AddressApi($this->transport);
    }

    /**
     * Returns a typed {@see RelationshipApi} for the Relationship entity.
     */
    public function relationships(): RelationshipApi
    {
        return new RelationshipApi($this->transport);
    }

    /**
     * Returns a typed {@see RelationshipTypeApi} for the RelationshipType entity.
     */
    public function relationshipTypes(): RelationshipTypeApi
    {
        return new RelationshipTypeApi($this->transport);
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
