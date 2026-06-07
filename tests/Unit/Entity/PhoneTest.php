<?php

declare(strict_types=1);

use Woduda\CiviCRM\Entity\Phone;

it('hydrates a phone from a full API row', function (): void {
    $row = fixtureFirstRow('phone_single.json');
    $phone = Phone::fromArray($row);

    expect($phone->id)->toBe(203)
        ->and($phone->contactId)->toBe(42)
        ->and($phone->phone)->toBe('+48987654321')
        ->and($phone->phoneType)->toBe('Mobile')
        ->and($phone->locationType)->toBe('Home')
        ->and($phone->isPrimary)->toBeTrue()
        ->and($phone->rawData)->toBe($row);
});

it('tolerates missing optional fields without throwing', function (): void {
    $phone = Phone::fromArray(['id' => 1, 'contact_id' => 42]);

    expect($phone->phone)->toBe('')
        ->and($phone->phoneType)->toBeNull()
        ->and($phone->isPrimary)->toBeFalse();
});

it('round-trips mapped fields through toArray', function (): void {
    $row = fixtureFirstRow('phone_single.json');
    $phone = Phone::fromArray($row);
    $exported = $phone->toArray();

    expect($exported)->toMatchArray([
        'id' => 203,
        'contact_id' => 42,
        'phone' => '+48987654321',
        'phone_type_id.name' => 'Mobile',
        'location_type_id.name' => 'Home',
        'is_primary' => true,
        'is_billing' => false,
    ]);
});
