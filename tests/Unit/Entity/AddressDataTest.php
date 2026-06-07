<?php

declare(strict_types=1);

use Woduda\CiviCRM\Entity\AddressData;

it('hydrates address data from snake_case keys', function (): void {
    $data = AddressData::fromArray([
        'street_address' => 'Main St 1',
        'supplemental_address_1' => 'Apt 2',
        'city' => 'Warsaw',
        'postal_code' => '00-001',
        'country' => 'PL',
        'state_province' => 'Mazovia',
    ]);

    expect($data->streetAddress)->toBe('Main St 1')
        ->and($data->supplementalAddress1)->toBe('Apt 2')
        ->and($data->city)->toBe('Warsaw')
        ->and($data->postalCode)->toBe('00-001')
        ->and($data->country)->toBe('PL')
        ->and($data->stateProvince)->toBe('Mazovia');
});

it('hydrates address data from camelCase keys', function (): void {
    $data = AddressData::fromArray([
        'streetAddress' => 'Main St 1',
        'city' => 'Warsaw',
        'postalCode' => '00-001',
    ]);

    expect($data->streetAddress)->toBe('Main St 1')
        ->and($data->supplementalAddress1)->toBeNull()
        ->and($data->country)->toBeNull()
        ->and($data->stateProvince)->toBeNull();
});

it('defaults missing optional fields to null', function (): void {
    $data = AddressData::fromArray([
        'street_address' => 'Main St 1',
        'city' => 'Warsaw',
        'postal_code' => '00-001',
    ]);

    expect($data->supplementalAddress1)->toBeNull()
        ->and($data->country)->toBeNull()
        ->and($data->stateProvince)->toBeNull();
});
