<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Api;

use Woduda\CiviCRM\Contract\TransportInterface;
use Woduda\CiviCRM\Entity\Address;
use Woduda\CiviCRM\Entity\AddressData;
use Woduda\CiviCRM\Exception\ValidationException;
use Woduda\CiviCRM\Query\GetQuery;
use Woduda\CiviCRM\Result\Result;
use Woduda\CiviCRM\Result\TypedResult;

/**
 * Typed API for the CiviCRM `Address` entity.
 *
 * Example:
 * ```php
 * $addresses = $client->addresses();
 *
 * $all = $addresses->forContact(42);
 * $addresses->addFromData(42, AddressData::fromArray([
 *     'street_address' => 'Main St 1',
 *     'city' => 'Warsaw',
 *     'postal_code' => '00-001',
 *     'country' => 'PL',
 * ]), isPrimary: true);
 * ```
 */
final readonly class AddressApi extends AbstractContactSubEntityApi
{
    public function __construct(TransportInterface $transport)
    {
        parent::__construct($transport, 'Address');
    }

    /**
     * Fetches address records matching the query.
     *
     * @return Result<Address>
     *
     * Example:
     * ```php
     * $api->get(GetQuery::new()->where('city', Operator::Equals, 'Warsaw'));
     * ```
     */
    public function get(GetQuery $query): Result
    {
        return TypedResult::hydrate($this->executeGet($query), Address::class);
    }

    /**
     * Returns all address records for a contact, primary first.
     *
     * @return Result<Address>
     *
     * Example:
     * ```php
     * $addresses = $api->forContact(42);
     * ```
     */
    public function forContact(int $contactId): Result
    {
        return TypedResult::hydrate(
            $this->executeGet($this->contactQuery($contactId)),
            Address::class,
        );
    }

    /**
     * Returns the primary address for a contact, or `null` if none is marked primary.
     *
     * Example:
     * ```php
     * $address = $api->primary(42);
     * ```
     */
    public function primary(int $contactId): ?Address
    {
        foreach ($this->forContact($contactId)->values as $address) {
            if ($address instanceof Address && $address->isPrimary) {
                return $address;
            }
        }

        return null;
    }

    /**
     * Marks the given address as primary.
     *
     * CiviCRM automatically unsets `is_primary` on all other address records for
     * that contact — no separate update calls are required.
     *
     * Example:
     * ```php
     * $api->setPrimary(301);
     * ```
     */
    #[\Override]
    public function setPrimary(int $id): void
    {
        parent::setPrimary($id);
    }

    /**
     * Creates a new address record for a contact.
     *
     * Example:
     * ```php
     * $address = $api->add(42, 'Main St 1', 'Warsaw', '00-001', countryId: 1072, isPrimary: true);
     * ```
     */
    public function add(
        int $contactId,
        string $streetAddress,
        string $city,
        string $postalCode,
        ?string $supplementalAddress1 = null,
        ?int $countryId = null,
        ?int $stateProvinceId = null,
        string $locationType = 'Home',
        bool $isPrimary = false,
    ): Address {
        $values = [
            'contact_id' => $contactId,
            'street_address' => $streetAddress,
            'city' => $city,
            'postal_code' => $postalCode,
            'location_type_id.name' => $locationType,
            'is_primary' => $isPrimary,
        ];

        if ($supplementalAddress1 !== null) {
            $values['supplemental_address_1'] = $supplementalAddress1;
        }

        if ($countryId !== null) {
            $values['country_id'] = $countryId;
        }

        if ($stateProvinceId !== null) {
            $values['state_province_id'] = $stateProvinceId;
        }

        $record = $this->createRecord($values);

        assert($record instanceof Address);

        return $record;
    }

    /**
     * Creates a new address after resolving country and state from {@see AddressData}.
     *
     * Example:
     * ```php
     * $address = $api->addFromData(42, AddressData::fromArray([
     *     'street_address' => 'Main St 1',
     *     'city' => 'Warsaw',
     *     'postal_code' => '00-001',
     *     'country' => 'PL',
     * ]));
     * ```
     *
     * @throws ValidationException When country or state/province cannot be resolved
     */
    public function addFromData(
        int $contactId,
        AddressData $data,
        string $locationType = 'Home',
        bool $isPrimary = false,
    ): Address {
        $countryId = null;
        $stateProvinceId = null;

        if ($data->country !== null) {
            $countryId = $this->resolveCountryId($data->country);
        }

        if ($data->stateProvince !== null) {
            if ($countryId === null) {
                throw ValidationException::unknownStateProvince($data->stateProvince);
            }

            $stateProvinceId = $this->resolveStateProvinceId($data->stateProvince, $countryId);
        }

        return $this->add(
            $contactId,
            $data->streetAddress,
            $data->city,
            $data->postalCode,
            $data->supplementalAddress1,
            $countryId,
            $stateProvinceId,
            $locationType,
            $isPrimary,
        );
    }

    /**
     * Deletes the address record with the given ID.
     *
     * Example:
     * ```php
     * $api->remove(301);
     * ```
     */
    #[\Override]
    public function remove(int $id): void
    {
        parent::remove($id);
    }

    /**
     * Updates a record by ID and returns the hydrated address.
     *
     * @param  array<string, mixed> $values
     */
    #[\Override]
    public function updateById(int $id, array $values): Address
    {
        $record = parent::updateById($id, $values);

        assert($record instanceof Address);

        return $record;
    }

    /**
     * Updates an address from {@see AddressData}, resolving country and state when provided.
     *
     * @throws ValidationException When country or state/province cannot be resolved
     */
    public function updateFromData(int $id, AddressData $data): Address
    {
        $values = [
            'street_address' => $data->streetAddress,
            'city' => $data->city,
            'postal_code' => $data->postalCode,
        ];

        if ($data->supplementalAddress1 !== null) {
            $values['supplemental_address_1'] = $data->supplementalAddress1;
        }

        if ($data->country !== null) {
            $values['country_id'] = $this->resolveCountryId($data->country);
        }

        if ($data->stateProvince !== null) {
            $countryId = $values['country_id'] ?? null;

            if (! is_int($countryId)) {
                throw ValidationException::unknownStateProvince($data->stateProvince);
            }

            $values['state_province_id'] = $this->resolveStateProvinceId($data->stateProvince, $countryId);
        }

        return $this->updateById($id, $values);
    }

    /**
     * Returns the field definitions for the Address entity.
     *
     * @return array<mixed>
     */
    #[\Override]
    public function getFields(): array
    {
        return parent::getFields();
    }

    /**
     * Returns the available actions for the Address entity.
     *
     * @return array<mixed>
     */
    #[\Override]
    public function getActions(): array
    {
        return parent::getActions();
    }

    #[\Override]
    protected function dtoClass(): string
    {
        return Address::class;
    }

    /**
     * @throws ValidationException
     */
    private function resolveCountryId(string $country): int
    {
        $field = strlen($country) === 2 ? 'iso_code' : 'name';

        $rows = $this->transport->send('Country', 'get', [
            'where' => [[$field, '=', $country]],
            'select' => ['id'],
            'limit' => 1,
        ])->values;

        $row = $rows[0] ?? null;
        $id = is_array($row) ? ($row['id'] ?? null) : null;

        if (! is_int($id)) {
            throw ValidationException::unknownCountry($country);
        }

        return $id;
    }

    /**
     * @throws ValidationException
     */
    private function resolveStateProvinceId(string $stateProvince, int $countryId): int
    {
        $rows = $this->transport->send('StateProvince', 'get', [
            'where' => [
                ['country_id', '=', $countryId],
                ['OR', [
                    ['name', '=', $stateProvince],
                    ['abbreviation', '=', $stateProvince],
                ]],
            ],
            'select' => ['id'],
            'limit' => 1,
        ])->values;

        $row = $rows[0] ?? null;
        $id = is_array($row) ? ($row['id'] ?? null) : null;

        if (! is_int($id)) {
            throw ValidationException::unknownStateProvince($stateProvince);
        }

        return $id;
    }
}
