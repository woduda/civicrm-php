<?php

declare(strict_types=1);

use Woduda\CiviCRM\Entity\Email;

it('hydrates an email from a full API row', function (): void {
    $row = fixtureFirstRow('email_single.json');
    $email = Email::fromArray($row);

    expect($email->id)->toBe(103)
        ->and($email->contactId)->toBe(42)
        ->and($email->email)->toBe('new@example.org')
        ->and($email->locationType)->toBe('Home')
        ->and($email->isPrimary)->toBeTrue()
        ->and($email->isBilling)->toBeFalse()
        ->and($email->onHold)->toBeFalse()
        ->and($email->rawData)->toBe($row);
});

it('tolerates missing optional fields without throwing', function (): void {
    $email = Email::fromArray(['id' => 1, 'contact_id' => 42]);

    expect($email->id)->toBe(1)
        ->and($email->contactId)->toBe(42)
        ->and($email->email)->toBe('')
        ->and($email->locationType)->toBe('')
        ->and($email->isPrimary)->toBeFalse()
        ->and($email->onHold)->toBeNull();
});

it('round-trips mapped fields through toArray', function (): void {
    $row = fixtureFirstRow('email_single.json');
    $email = Email::fromArray($row);
    $exported = $email->toArray();

    expect($exported)->toMatchArray([
        'id' => 103,
        'contact_id' => 42,
        'email' => 'new@example.org',
        'location_type_id.name' => 'Home',
        'is_primary' => true,
        'is_billing' => false,
        'on_hold' => false,
    ]);
});

it('coerces integer is_primary to bool', function (): void {
    $email = Email::fromArray(['id' => 1, 'contact_id' => 42, 'is_primary' => 1]);

    expect($email->isPrimary)->toBeTrue();
});
