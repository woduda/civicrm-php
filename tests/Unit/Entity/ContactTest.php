<?php

declare(strict_types=1);

use Woduda\CiviCRM\Entity\Contact;

it('hydrates a contact from a full API row', function (): void {
    $row = fixtureFirstRow('contact-single.json');
    $contact = Contact::fromArray($row);

    expect($contact->id)->toBe(42)
        ->and($contact->contactType)->toBe('Individual')
        ->and($contact->displayName)->toBe('Jane Doe')
        ->and($contact->firstName)->toBe('Jane')
        ->and($contact->lastName)->toBe('Doe')
        ->and($contact->email)->toBe('jane@example.org')
        ->and($contact->rawData)->toBe($row);
});

it('tolerates missing optional fields without throwing', function (): void {
    $contact = Contact::fromArray(['id' => 1]);

    expect($contact->id)->toBe(1)
        ->and($contact->contactType)->toBeNull()
        ->and($contact->displayName)->toBeNull()
        ->and($contact->firstName)->toBeNull()
        ->and($contact->lastName)->toBeNull()
        ->and($contact->email)->toBeNull();
});

it('round-trips mapped fields through toArray', function (): void {
    $row = fixtureFirstRow('contact-single.json');
    $contact = Contact::fromArray($row);
    $exported = $contact->toArray();

    expect($exported)->toMatchArray([
        'id' => 42,
        'contact_type' => 'Individual',
        'display_name' => 'Jane Doe',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.org',
    ]);

    $roundTrip = Contact::fromArray($exported);

    expect($roundTrip->id)->toBe($contact->id)
        ->and($roundTrip->contactType)->toBe($contact->contactType)
        ->and($roundTrip->displayName)->toBe($contact->displayName)
        ->and($roundTrip->firstName)->toBe($contact->firstName)
        ->and($roundTrip->lastName)->toBe($contact->lastName)
        ->and($roundTrip->email)->toBe($contact->email);
});

it('prefers email_primary.email over email when both are present', function (): void {
    $contact = Contact::fromArray([
        'email' => 'fallback@example.org',
        'email_primary.email' => 'primary@example.org',
    ]);

    expect($contact->email)->toBe('primary@example.org');
});
