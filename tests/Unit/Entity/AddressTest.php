<?php

declare(strict_types=1);

use Woduda\CiviCRM\Entity\Address;

it('hydrates an address from a full API row', function (): void {
    $row = fixtureFirstRow('addresses_for_contact.json');
    $address = Address::fromArray($row);

    expect($address->id)->toBe(301)
        ->and($address->contactId)->toBe(42)
        ->and($address->streetAddress)->toBe('Main St 1')
        ->and($address->supplementalAddress1)->toBe('Apt 2')
        ->and($address->city)->toBe('Warsaw')
        ->and($address->postalCode)->toBe('00-001')
        ->and($address->countryId)->toBe(1072)
        ->and($address->stateProvinceId)->toBe(1060)
        ->and($address->isPrimary)->toBeTrue()
        ->and($address->rawData)->toBe($row);
});

it('tolerates missing optional fields without throwing', function (): void {
    $address = Address::fromArray(['id' => 1, 'contact_id' => 42]);

    expect($address->streetAddress)->toBe('')
        ->and($address->city)->toBe('')
        ->and($address->postalCode)->toBe('')
        ->and($address->locationType)->toBe('')
        ->and($address->isPrimary)->toBeFalse()
        ->and($address->isBilling)->toBeFalse()
        ->and($address->supplementalAddress1)->toBeNull()
        ->and($address->countryId)->toBeNull()
        ->and($address->stateProvinceId)->toBeNull();
});

it('round-trips mapped fields through toArray', function (): void {
    $row = fixtureFirstRow('address_single.json');
    $address = Address::fromArray($row);
    $exported = $address->toArray();

    expect($exported)->toMatchArray([
        'id' => 303,
        'contact_id' => 42,
        'street_address' => 'New St 10',
        'city' => 'Gdansk',
        'postal_code' => '80-001',
        'country_id' => 1072,
        'location_type_id.name' => 'Home',
        'is_primary' => true,
        'is_billing' => false,
    ]);
});

it('toArray omits location_type_id.name when locationType is empty string', function (): void {
    $address = Address::fromArray(['id' => 1, 'contact_id' => 42]);

    expect($address->toArray())->not->toHaveKey('location_type_id.name');
});

it('toArray omits supplemental_address_1 when supplementalAddress1 is null', function (): void {
    $address = Address::fromArray(['id' => 1, 'contact_id' => 42, 'street_address' => 'St 1']);

    expect($address->toArray())->not->toHaveKey('supplemental_address_1');
});

it('toArray omits state_province_id when stateProvinceId is null', function (): void {
    $address = Address::fromArray(['id' => 1, 'contact_id' => 42]);

    expect($address->toArray())->not->toHaveKey('state_province_id');
});

it('toInt returns 0 when id is not an integer', function (): void {
    $address = Address::fromArray(['id' => 'not-an-int', 'contact_id' => null]);

    expect($address->id)->toBe(0)
        ->and($address->contactId)->toBe(0);
});

it('toBool returns true for string "1" as is_primary', function (): void {
    $address = Address::fromArray(['id' => 1, 'contact_id' => 42, 'is_primary' => '1']);

    expect($address->isPrimary)->toBeTrue();
});

it('toBool returns true for integer 1 as is_primary', function (): void {
    $address = Address::fromArray(['id' => 1, 'contact_id' => 42, 'is_primary' => 1]);

    expect($address->isPrimary)->toBeTrue();
});

it('toBool returns true for boolean true in is_billing', function (): void {
    $address = Address::fromArray(['id' => 1, 'contact_id' => 42, 'is_billing' => true]);

    expect($address->isBilling)->toBeTrue();
});
