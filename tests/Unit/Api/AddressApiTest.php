<?php

declare(strict_types=1);

use Woduda\CiviCRM\Api\AddressApi;
use Woduda\CiviCRM\Entity\Address;
use Woduda\CiviCRM\Entity\AddressData;
use Woduda\CiviCRM\Exception\ValidationException;
use Woduda\CiviCRM\Query\GetQuery;
use Woduda\CiviCRM\Query\Operator;
use Woduda\CiviCRM\Result\ApiResponse;

function makeAddressApi(SpyTransport $spy): AddressApi
{
    return new AddressApi($spy);
}

it('get returns a Result of Address DTOs from the transport response', function (): void {
    $spy = new SpyTransport();
    $payload = fixtureApiPayload('address_single.json');
    $spy->queue(new ApiResponse(4, $payload['count'], $payload['values']));

    $result = makeAddressApi($spy)->get(GetQuery::new());

    expect($result->first())->toBeInstanceOf(Address::class)
        ->and($result->first()?->city)->toBe('Gdansk');
});

it('forContact sends get with contact_id where and is_primary order', function (): void {
    $spy = new SpyTransport();
    $payload = fixtureApiPayload('addresses_for_contact.json');
    $spy->queue(new ApiResponse(4, $payload['count'], $payload['values']));

    makeAddressApi($spy)->forContact(42);

    expect($spy->calls[0]['entity'])->toBe('Address')
        ->and($spy->calls[0]['params']['where'])->toBe(
            GetQuery::new()->where('contact_id', Operator::Equals, 42)->toParams()['where'],
        )
        ->and($spy->calls[0]['params']['orderBy'])->toBe(['is_primary' => 'DESC']);
});

it('primary returns the address marked is_primary', function (): void {
    $spy = new SpyTransport();
    $payload = fixtureApiPayload('addresses_for_contact.json');
    $spy->queue(new ApiResponse(4, $payload['count'], $payload['values']));

    $primary = makeAddressApi($spy)->primary(42);

    expect($primary)->toBeInstanceOf(Address::class)
        ->and($primary?->id)->toBe(301)
        ->and($primary?->isPrimary)->toBeTrue();
});

it('setPrimary sends a single update with is_primary=true', function (): void {
    $spy = new SpyTransport();

    makeAddressApi($spy)->setPrimary(301);

    expect($spy->calls)->toHaveCount(1)
        ->and($spy->calls[0]['action'])->toBe('update')
        ->and($spy->calls[0]['params']['values'])->toBe(['is_primary' => true])
        ->and($spy->calls[0]['params']['where'])->toBe([['id', '=', 301]]);
});

it('add sends action=create with address fields', function (): void {
    $spy = new SpyTransport();
    $payload = fixtureApiPayload('address_single.json');
    $spy->queue(new ApiResponse(4, $payload['count'], $payload['values']));

    makeAddressApi($spy)->add(42, 'New St 10', 'Gdansk', '80-001', countryId: 1072, isPrimary: true);

    expect($spy->calls[0]['params']['values'])->toBe([
        'contact_id' => 42,
        'street_address' => 'New St 10',
        'city' => 'Gdansk',
        'postal_code' => '80-001',
        'location_type_id.name' => 'Home',
        'is_primary' => true,
        'country_id' => 1072,
    ]);
});

it('addFromData resolves country by ISO-2 then creates address', function (): void {
    $spy = new SpyTransport();
    $country = fixtureApiPayload('country_found.json');
    $address = fixtureApiPayload('address_single.json');
    $spy->queue(new ApiResponse(4, $country['count'], $country['values']));
    $spy->queue(new ApiResponse(4, $address['count'], $address['values']));

    $data = AddressData::fromArray([
        'street_address' => 'New St 10',
        'city' => 'Gdansk',
        'postal_code' => '80-001',
        'country' => 'PL',
    ]);

    $result = makeAddressApi($spy)->addFromData(42, $data, isPrimary: true);

    expect($spy->calls[0]['entity'])->toBe('Country')
        ->and($spy->calls[0]['params']['where'])->toBe([['iso_code', '=', 'PL']])
        ->and($spy->calls[1]['entity'])->toBe('Address')
        ->and($spy->calls[1]['action'])->toBe('create')
        ->and($result)->toBeInstanceOf(Address::class);
});

it('addFromData resolves country and state then creates address', function (): void {
    $spy = new SpyTransport();
    $country = fixtureApiPayload('country_found.json');
    $state = fixtureApiPayload('state_province_found.json');
    $address = fixtureApiPayload('address_single.json');
    $spy->queue(new ApiResponse(4, $country['count'], $country['values']));
    $spy->queue(new ApiResponse(4, $state['count'], $state['values']));
    $spy->queue(new ApiResponse(4, $address['count'], $address['values']));

    $data = AddressData::fromArray([
        'street_address' => 'Main St 1',
        'city' => 'Warsaw',
        'postal_code' => '00-001',
        'country' => 'PL',
        'state_province' => 'Mazovia',
    ]);

    makeAddressApi($spy)->addFromData(42, $data);

    /** @var array<string, mixed> $createValues */
    $createValues = $spy->calls[2]['params']['values'];
    expect($spy->calls[1]['entity'])->toBe('StateProvince')
        ->and($spy->calls[2]['action'])->toBe('create')
        ->and($createValues['state_province_id'])->toBe(1060);
});

it('addFromData throws ValidationException for unknown country', function (): void {
    $spy = new SpyTransport();

    $data = AddressData::fromArray([
        'street_address' => 'Main St 1',
        'city' => 'Nowhere',
        'postal_code' => '00-000',
        'country' => 'XX',
    ]);

    expect(fn() => makeAddressApi($spy)->addFromData(42, $data))
        ->toThrow(ValidationException::class, 'Country "XX" does not exist.');
});

it('addFromData throws ValidationException for unknown state without country', function (): void {
    $spy = new SpyTransport();

    $data = AddressData::fromArray([
        'street_address' => 'Main St 1',
        'city' => 'Warsaw',
        'postal_code' => '00-001',
        'state_province' => 'Unknown',
    ]);

    expect(fn() => makeAddressApi($spy)->addFromData(42, $data))
        ->toThrow(ValidationException::class, 'State/province "Unknown" does not exist.');
});

it('remove sends action=delete with where id = $id', function (): void {
    $spy = new SpyTransport();

    makeAddressApi($spy)->remove(301);

    expect($spy->calls[0]['action'])->toBe('delete')
        ->and($spy->calls[0]['params']['where'])->toBe([['id', '=', 301]]);
});

it('getFields sends entity=Address and action=getfields', function (): void {
    $spy = new SpyTransport();

    makeAddressApi($spy)->getFields();

    expect($spy->calls[0]['entity'])->toBe('Address')
        ->and($spy->calls[0]['action'])->toBe('getfields');
});

it('updateFromData resolves country and updates the address', function (): void {
    $spy = new SpyTransport();
    $country = fixtureApiPayload('country_found.json');
    $updated = fixtureApiPayload('address_single.json');
    $spy->queue(new ApiResponse(4, $country['count'], $country['values']));
    $spy->queue(new ApiResponse(4, $updated['count'], $updated['values']));

    $data = AddressData::fromArray([
        'street_address' => 'New St 10',
        'city' => 'Gdansk',
        'postal_code' => '80-001',
        'country' => 'PL',
    ]);

    $address = makeAddressApi($spy)->updateFromData(301, $data);

    expect($address)->toBeInstanceOf(Address::class)
        ->and($spy->calls[1]['action'])->toBe('update');
});

it('addFromData throws ValidationException for unknown state when country resolves', function (): void {
    $spy = new SpyTransport();
    $country = fixtureApiPayload('country_found.json');
    $spy->queue(new ApiResponse(4, $country['count'], $country['values']));

    $data = AddressData::fromArray([
        'street_address' => 'Main St 1',
        'city' => 'Warsaw',
        'postal_code' => '00-001',
        'country' => 'PL',
        'state_province' => 'Unknown',
    ]);

    expect(fn() => makeAddressApi($spy)->addFromData(42, $data))
        ->toThrow(ValidationException::class, 'State/province "Unknown" does not exist.');
});

it('add resolves country by name when not ISO-2', function (): void {
    $spy = new SpyTransport();
    $country = fixtureApiPayload('country_found.json');
    $address = fixtureApiPayload('address_single.json');
    $spy->queue(new ApiResponse(4, $country['count'], $country['values']));
    $spy->queue(new ApiResponse(4, $address['count'], $address['values']));

    $data = AddressData::fromArray([
        'street_address' => 'New St 10',
        'city' => 'Gdansk',
        'postal_code' => '80-001',
        'country' => 'Poland',
    ]);

    makeAddressApi($spy)->addFromData(42, $data);

    expect($spy->calls[0]['params']['where'])->toBe([['name', '=', 'Poland']]);
});

it('add includes supplemental address when provided', function (): void {
    $spy = new SpyTransport();
    $payload = fixtureApiPayload('address_single.json');
    $spy->queue(new ApiResponse(4, $payload['count'], $payload['values']));

    makeAddressApi($spy)->add(42, 'Main St 1', 'Warsaw', '00-001', supplementalAddress1: 'Apt 2');

    /** @var array<string, mixed> $values */
    $values = $spy->calls[0]['params']['values'];
    expect($values['supplemental_address_1'])->toBe('Apt 2');
});

it('updateById sends action=update and returns hydrated Address', function (): void {
    $spy = new SpyTransport();
    $payload = fixtureApiPayload('address_single.json');
    $spy->queue(new ApiResponse(4, $payload['count'], $payload['values']));

    $address = makeAddressApi($spy)->updateById(301, ['city' => 'Poznan']);

    expect($address)->toBeInstanceOf(Address::class)
        ->and($spy->calls[0]['action'])->toBe('update');
});

it('primary returns null when forContact is empty', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 0, []));

    expect(makeAddressApi($spy)->primary(42))->toBeNull();
});

it('add defaults is_primary to false when not specified', function (): void {
    $spy = new SpyTransport();
    $payload = fixtureApiPayload('address_single.json');
    $spy->queue(new ApiResponse(4, $payload['count'], $payload['values']));

    makeAddressApi($spy)->add(42, 'Main St 1', 'Warsaw', '00-001');

    /** @var array<string, mixed> $values */
    $values = $spy->calls[0]['params']['values'];
    expect($values['is_primary'])->toBeFalse();
});

it('addFromData defaults is_primary to false when not specified', function (): void {
    $spy = new SpyTransport();
    $country = fixtureApiPayload('country_found.json');
    $address = fixtureApiPayload('address_single.json');
    $spy->queue(new ApiResponse(4, $country['count'], $country['values']));
    $spy->queue(new ApiResponse(4, $address['count'], $address['values']));

    $data = AddressData::fromArray([
        'street_address' => 'Main St 1',
        'city' => 'Warsaw',
        'postal_code' => '00-001',
        'country' => 'PL',
    ]);

    makeAddressApi($spy)->addFromData(42, $data);

    /** @var array<string, mixed> $values */
    $values = $spy->calls[1]['params']['values'];
    expect($values['is_primary'])->toBeFalse();
});

it('primary skips non-primary addresses and returns the primary one', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 2, [
        ['id' => 302, 'contact_id' => 42, 'street_address' => 'Work Blvd 5', 'city' => 'Krakow',
            'postal_code' => '30-001', 'is_primary' => false],
        ['id' => 301, 'contact_id' => 42, 'street_address' => 'Main St 1', 'city' => 'Warsaw',
            'postal_code' => '00-001', 'is_primary' => true],
    ]));

    $primary = makeAddressApi($spy)->primary(42);

    expect($primary?->id)->toBe(301);
});

it('updateFromData includes supplemental address when provided', function (): void {
    $spy = new SpyTransport();
    $payload = fixtureApiPayload('address_single.json');
    $spy->queue(new ApiResponse(4, $payload['count'], $payload['values']));

    $data = AddressData::fromArray([
        'street_address' => 'Main St 1',
        'city' => 'Warsaw',
        'postal_code' => '00-001',
        'supplemental_address_1' => 'Floor 2',
    ]);

    makeAddressApi($spy)->updateFromData(301, $data);

    /** @var array<string, mixed> $values */
    $values = $spy->calls[0]['params']['values'];
    expect($values['supplemental_address_1'])->toBe('Floor 2');
});

it('updateFromData throws ValidationException when stateProvince set but country_id not yet resolved', function (): void {
    $spy = new SpyTransport();

    $data = AddressData::fromArray([
        'street_address' => 'Main St 1',
        'city' => 'Warsaw',
        'postal_code' => '00-001',
        'state_province' => 'Mazovia',
    ]);

    expect(fn() => makeAddressApi($spy)->updateFromData(301, $data))
        ->toThrow(ValidationException::class);
});

it('updateFromData resolves state province when both country and state are provided', function (): void {
    $spy = new SpyTransport();
    $country = fixtureApiPayload('country_found.json');
    $state = fixtureApiPayload('state_province_found.json');
    $updated = fixtureApiPayload('address_single.json');
    $spy->queue(new ApiResponse(4, $country['count'], $country['values']));
    $spy->queue(new ApiResponse(4, $state['count'], $state['values']));
    $spy->queue(new ApiResponse(4, $updated['count'], $updated['values']));

    $data = AddressData::fromArray([
        'street_address' => 'Main St 1',
        'city' => 'Warsaw',
        'postal_code' => '00-001',
        'country' => 'PL',
        'state_province' => 'Mazovia',
    ]);

    $address = makeAddressApi($spy)->updateFromData(301, $data);

    /** @var array<string, mixed> $values */
    $values = $spy->calls[2]['params']['values'];
    expect($address)->toBeInstanceOf(Address::class)
        ->and($values)->toHaveKey('state_province_id')
        ->and($spy->calls[1]['entity'])->toBe('StateProvince')
        ->and($spy->calls[1]['params']['where'])->toBe([
            ['country_id', '=', 1072],
            ['OR', [
                ['name', '=', 'Mazovia'],
                ['abbreviation', '=', 'Mazovia'],
            ]],
        ])
        ->and($spy->calls[1]['params']['select'])->toBe(['id'])
        ->and($spy->calls[1]['params']['limit'])->toBe(1);
});

it('getActions sends entity=Address and action=getactions', function (): void {
    $spy = new SpyTransport();

    makeAddressApi($spy)->getActions();

    expect($spy->calls[0]['entity'])->toBe('Address')
        ->and($spy->calls[0]['action'])->toBe('getactions');
});
