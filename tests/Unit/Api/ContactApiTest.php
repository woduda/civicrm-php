<?php

declare(strict_types=1);

use Woduda\CiviCRM\Api\ContactApi;
use Woduda\CiviCRM\Api\CustomFieldResolver;
use Woduda\CiviCRM\Entity\Address;
use Woduda\CiviCRM\Entity\AddressData;
use Woduda\CiviCRM\Entity\Contact;
use Woduda\CiviCRM\Entity\Email;
use Woduda\CiviCRM\Entity\Phone;
use Woduda\CiviCRM\Exception\ValidationException;
use Woduda\CiviCRM\Query\GetQuery;
use Woduda\CiviCRM\Query\Operator;
use Woduda\CiviCRM\Result\ApiResponse;
use Woduda\CiviCRM\Result\Result;

function makeContactApi(SpyTransport $spy): ContactApi
{
    return new ContactApi($spy, new CustomFieldResolver($spy));
}

// ---------------------------------------------------------------------------
// get
// ---------------------------------------------------------------------------

it('get sends entity=Contact, action=get, and compiled params', function (): void {
    $spy = new SpyTransport();
    $query = GetQuery::new()->select('id', 'display_name')->limit(10);

    makeContactApi($spy)->get($query);

    expect($spy->calls[0]['entity'])->toBe('Contact')
        ->and($spy->calls[0]['action'])->toBe('get')
        ->and($spy->calls[0]['params'])->toBe($query->toParams());
});

it('get returns a Result of Contact DTOs from the transport response', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 42, 'display_name' => 'Jane Doe']]));

    $result = makeContactApi($spy)->get(GetQuery::new());

    expect($result)->toBeInstanceOf(Result::class)
        ->and($result->count())->toBe(1)
        ->and($result->first())->toBeInstanceOf(Contact::class)
        ->and($result->first()?->id)->toBe(42)
        ->and($result->first()?->displayName)->toBe('Jane Doe');
});

// ---------------------------------------------------------------------------
// getById
// ---------------------------------------------------------------------------

it('getById returns the first matching contact', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 42, 'display_name' => 'Jane Doe']]));

    $contact = makeContactApi($spy)->getById(42);

    expect($contact)->toBeInstanceOf(Contact::class)
        ->and($contact?->id)->toBe(42)
        ->and($contact?->displayName)->toBe('Jane Doe')
        ->and($spy->calls[0]['params']['where'])->toBe(
            GetQuery::new()->where('id', Operator::Equals, 42)->toParams()['where'],
        );
});

it('getById returns null when no contact is found', function (): void {
    $spy = new SpyTransport();

    $contact = makeContactApi($spy)->getById(999);

    expect($contact)->toBeNull();
});

// ---------------------------------------------------------------------------
// create
// ---------------------------------------------------------------------------

it('create sends action=create with the given values', function (): void {
    $spy = new SpyTransport();

    makeContactApi($spy)->create(['contact_type' => 'Individual', 'first_name' => 'Jane']);

    expect($spy->calls[0]['entity'])->toBe('Contact')
        ->and($spy->calls[0]['action'])->toBe('create')
        ->and($spy->calls[0]['params']['values'])->toBe([
            'contact_type' => 'Individual',
            'first_name' => 'Jane',
        ]);
});

it('create returns a Result of Contact DTOs from the transport response', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 99, 'first_name' => 'Jane']]));

    $result = makeContactApi($spy)->create(['first_name' => 'Jane']);

    expect($result)->toBeInstanceOf(Result::class)
        ->and($result->first())->toBeInstanceOf(Contact::class)
        ->and($result->first()?->id)->toBe(99)
        ->and($result->first()?->firstName)->toBe('Jane');
});

// ---------------------------------------------------------------------------
// update
// ---------------------------------------------------------------------------

it('update sends action=update with values and where id = $id', function (): void {
    $spy = new SpyTransport();

    makeContactApi($spy)->update(42, ['last_name' => 'Doe']);

    expect($spy->calls[0]['action'])->toBe('update')
        ->and($spy->calls[0]['params']['values'])->toBe(['last_name' => 'Doe'])
        ->and($spy->calls[0]['params']['where'])->toBe([['id', '=', 42]]);
});

// ---------------------------------------------------------------------------
// getFields / getActions
// ---------------------------------------------------------------------------

it('getFields sends entity=Contact and action=getfields', function (): void {
    $spy = new SpyTransport();

    makeContactApi($spy)->getFields();

    expect($spy->calls[0]['entity'])->toBe('Contact')
        ->and($spy->calls[0]['action'])->toBe('getfields');
});

it('getActions sends entity=Contact and action=getactions', function (): void {
    $spy = new SpyTransport();

    makeContactApi($spy)->getActions();

    expect($spy->calls[0]['entity'])->toBe('Contact')
        ->and($spy->calls[0]['action'])->toBe('getactions');
});

// ---------------------------------------------------------------------------
// upsertByEmail
// ---------------------------------------------------------------------------

it('upsertByEmail updates the existing contact when found by email', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 42]])); // Contact.get → found

    makeContactApi($spy)->upsertByEmail('jane@example.org', ['first_name' => 'Jane']);

    expect($spy->calls)->toHaveCount(2)
        ->and($spy->calls[0]['action'])->toBe('get')
        ->and($spy->calls[0]['params']['where'])->toBe([['email_primary.email', '=', 'jane@example.org']])
        ->and($spy->calls[1]['action'])->toBe('update')
        ->and($spy->calls[1]['params']['where'])->toBe([['id', '=', 42]])
        ->and($spy->calls[1]['params']['values'])->toBe(['first_name' => 'Jane']);
});

it('upsertByEmail creates a new contact with email merged when not found', function (): void {
    $spy = new SpyTransport();

    makeContactApi($spy)->upsertByEmail('new@example.org', ['first_name' => 'New']);

    expect($spy->calls)->toHaveCount(2)
        ->and($spy->calls[0]['action'])->toBe('get')
        ->and($spy->calls[1]['action'])->toBe('create')
        ->and($spy->calls[1]['params']['values'])->toBe([
            'first_name' => 'New',
            'email' => 'new@example.org',
        ]);
});

it('upsertByEmail uses select=id and limit=1 in the lookup query', function (): void {
    $spy = new SpyTransport();

    makeContactApi($spy)->upsertByEmail('jane@example.org', []);

    expect($spy->calls[0]['params']['select'])->toBe(['id'])
        ->and($spy->calls[0]['params']['limit'])->toBe(1);
});

// ---------------------------------------------------------------------------
// withTags
// ---------------------------------------------------------------------------

it('withTags does nothing when the tag list is empty', function (): void {
    $spy = new SpyTransport();

    makeContactApi($spy)->withTags(42, []);

    expect($spy->calls)->toHaveCount(0);
});

it('withTags sends select=[id,name] in the Tag.get query', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 7, 'name' => 'VIP']]));

    makeContactApi($spy)->withTags(42, ['VIP']);

    expect($spy->calls[0]['params']['select'])->toBe(['id', 'name']);
});

it('withTags uses fallback tag_id 0 when Tag.create returns no records', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 0, []));  // Tag.get → not found
    $spy->queue(new ApiResponse(4, 0, []));  // Tag.create → empty

    makeContactApi($spy)->withTags(42, ['Ghost']);

    /** @var list<array<string, mixed>> $records */
    $records = $spy->calls[2]['params']['records'];
    expect($records[0]['tag_id'])->toBe(0);
});

it('withTags assigns existing tags without creating new ones', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 2, [
        ['id' => 7, 'name' => 'VIP'],
        ['id' => 8, 'name' => 'Donor'],
    ]));

    makeContactApi($spy)->withTags(42, ['VIP', 'Donor']);

    expect($spy->calls)->toHaveCount(2)
        ->and($spy->calls[0]['entity'])->toBe('Tag')
        ->and($spy->calls[0]['action'])->toBe('get')
        ->and($spy->calls[0]['params']['where'])->toBe([['name', 'IN', ['VIP', 'Donor']]])
        ->and($spy->calls[1]['entity'])->toBe('EntityTag')
        ->and($spy->calls[1]['action'])->toBe('save')
        ->and($spy->calls[1]['params']['records'])->toHaveCount(2)
        ->and($spy->calls[1]['params']['match'])->toBe(['entity_id', 'tag_id', 'entity_table']);
});

it('withTags creates missing tags before saving EntityTag records', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 7, 'name' => 'VIP']])); // Tag.get → VIP found
    $spy->queue(new ApiResponse(4, 1, [['id' => 9, 'name' => 'New']])); // Tag.create → New

    makeContactApi($spy)->withTags(42, ['VIP', 'New']);

    expect($spy->calls)->toHaveCount(3)
        ->and($spy->calls[1]['action'])->toBe('create')
        ->and($spy->calls[1]['params']['values'])->toBe([
            'name' => 'New',
            'used_for' => 'civicrm_contact',
        ])
        ->and($spy->calls[2]['entity'])->toBe('EntityTag')
        ->and($spy->calls[2]['params']['records'])->toBe([
            ['entity_id' => 42, 'tag_id' => 7, 'entity_table' => 'civicrm_contact'],
            ['entity_id' => 42, 'tag_id' => 9, 'entity_table' => 'civicrm_contact'],
        ]);
});

// ---------------------------------------------------------------------------
// addToGroups
// ---------------------------------------------------------------------------

it('addToGroups does nothing when the group list is empty', function (): void {
    $spy = new SpyTransport();

    makeContactApi($spy)->addToGroups(42, []);

    expect($spy->calls)->toHaveCount(0);
});

it('addToGroups assigns existing groups without creating new ones', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 3, 'title' => 'Newsletter']]));

    makeContactApi($spy)->addToGroups(42, ['Newsletter']);

    expect($spy->calls)->toHaveCount(2)
        ->and($spy->calls[0]['entity'])->toBe('Group')
        ->and($spy->calls[0]['action'])->toBe('get')
        ->and($spy->calls[1]['entity'])->toBe('GroupContact')
        ->and($spy->calls[1]['action'])->toBe('save')
        ->and($spy->calls[1]['params']['records'])->toBe([[
            'contact_id' => 42,
            'group_id' => 3,
            'status' => 'Added',
        ]])
        ->and($spy->calls[1]['params']['match'])->toBe(['contact_id', 'group_id']);
});

it('addToGroups creates missing groups before saving GroupContact records', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 0, []));            // Group.get → not found
    $spy->queue(new ApiResponse(4, 1, [['id' => 5]])); // Group.create → new id

    makeContactApi($spy)->addToGroups(42, ['NewGroup']);

    expect($spy->calls)->toHaveCount(3)
        ->and($spy->calls[1]['action'])->toBe('create')
        ->and($spy->calls[2]['params']['records'])->toBe([[
            'contact_id' => 42,
            'group_id' => 5,
            'status' => 'Added',
        ]]);
});

it('addToGroups sends select=[id,title] and correct where in the Group.get query', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 3, 'title' => 'Newsletter']]));

    makeContactApi($spy)->addToGroups(42, ['Newsletter']);

    expect($spy->calls[0]['params']['select'])->toBe(['id', 'title'])
        ->and($spy->calls[0]['params']['where'])->toBe([['title', 'IN', ['Newsletter']]]);
});

it('addToGroups sends correct values in Group.create', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 0, []));
    $spy->queue(new ApiResponse(4, 1, [['id' => 5]]));

    makeContactApi($spy)->addToGroups(42, ['NewGroup']);

    expect($spy->calls[1]['params']['values'])->toBe(['title' => 'NewGroup']);
});

it('addToGroups uses fallback group_id 0 when Group.create returns no records', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 0, []));  // Group.get → not found
    $spy->queue(new ApiResponse(4, 0, []));  // Group.create → empty

    makeContactApi($spy)->addToGroups(42, ['Ghost']);

    /** @var list<array<string, mixed>> $records */
    $records = $spy->calls[2]['params']['records'];
    expect($records[0]['group_id'])->toBe(0);
});

// ---------------------------------------------------------------------------
// setCustomFields
// ---------------------------------------------------------------------------

it('setCustomFields resolves field names and sends a Contact.update', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 11]])); // CustomField.get → field found

    makeContactApi($spy)->setCustomFields(42, 'Wolontariat', ['volunteer_status' => 'active']);

    expect($spy->calls)->toHaveCount(2)
        ->and($spy->calls[0]['entity'])->toBe('CustomField')
        ->and($spy->calls[1]['entity'])->toBe('Contact')
        ->and($spy->calls[1]['action'])->toBe('update')
        ->and($spy->calls[1]['params']['values'])->toBe(['Wolontariat.volunteer_status' => 'active'])
        ->and($spy->calls[1]['params']['where'])->toBe([['id', '=', 42]]);
});

it('setCustomFields throws ValidationException for unknown custom field', function (): void {
    $spy = new SpyTransport();

    expect(fn() => makeContactApi($spy)->setCustomFields(42, 'Wolontariat', ['nonexistent' => 'x']))
        ->toThrow(ValidationException::class);
});

it('setCustomFields resolves each field separately and sends one Contact.update', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 11]])); // CustomField.get for field_a
    $spy->queue(new ApiResponse(4, 1, [['id' => 12]])); // CustomField.get for field_b

    makeContactApi($spy)->setCustomFields(42, 'Grp', ['field_a' => 1, 'field_b' => 2]);

    // 2 CustomField.get calls + 1 Contact.update
    expect($spy->calls)->toHaveCount(3)
        ->and($spy->calls[2]['params']['values'])->toBe([
            'Grp.field_a' => 1,
            'Grp.field_b' => 2,
        ]);
});

// ---------------------------------------------------------------------------
// updatePrimaryEmail / updatePrimaryPhone / updatePrimaryAddress
// ---------------------------------------------------------------------------

it('updatePrimaryEmail updates existing primary email', function (): void {
    $spy = new SpyTransport();
    $forContact = fixtureApiPayload('emails_for_contact.json');
    $updated = fixtureApiPayload('email_single.json');
    $spy->queue(new ApiResponse(4, $forContact['count'], $forContact['values']));
    $spy->queue(new ApiResponse(4, $updated['count'], $updated['values']));

    $email = makeContactApi($spy)->updatePrimaryEmail(42, 'updated@example.org');

    expect($email)->toBeInstanceOf(Email::class)
        ->and($spy->calls)->toHaveCount(2)
        ->and($spy->calls[0]['entity'])->toBe('Email')
        ->and($spy->calls[0]['action'])->toBe('get')
        ->and($spy->calls[1]['action'])->toBe('update')
        ->and($spy->calls[1]['params']['values'])->toBe(['email' => 'updated@example.org'])
        ->and($spy->calls[1]['params']['where'])->toBe([['id', '=', 101]]);
});

it('updatePrimaryEmail creates primary email when none exists', function (): void {
    $spy = new SpyTransport();
    $created = fixtureApiPayload('email_single.json');
    $spy->queue(new ApiResponse(4, 0, []));
    $spy->queue(new ApiResponse(4, $created['count'], $created['values']));

    $email = makeContactApi($spy)->updatePrimaryEmail(42, 'new@example.org');

    expect($email)->toBeInstanceOf(Email::class)
        ->and($spy->calls)->toHaveCount(2)
        ->and($spy->calls[0]['action'])->toBe('get')
        ->and($spy->calls[1]['action'])->toBe('create');

    /** @var array<string, mixed> $values */
    $values = $spy->calls[1]['params']['values'];
    expect($values['is_primary'])->toBeTrue();
});

it('updatePrimaryPhone updates existing primary phone', function (): void {
    $spy = new SpyTransport();
    $forContact = fixtureApiPayload('phones_for_contact.json');
    $updated = fixtureApiPayload('phone_single.json');
    $spy->queue(new ApiResponse(4, $forContact['count'], $forContact['values']));
    $spy->queue(new ApiResponse(4, $updated['count'], $updated['values']));

    $phone = makeContactApi($spy)->updatePrimaryPhone(42, '+48999999999', 'Mobile');

    expect($phone)->toBeInstanceOf(Phone::class)
        ->and($spy->calls)->toHaveCount(2)
        ->and($spy->calls[1]['action'])->toBe('update')
        ->and($spy->calls[1]['params']['values'])->toBe([
            'phone' => '+48999999999',
            'phone_type_id.name' => 'Mobile',
        ]);
});

it('updatePrimaryPhone creates primary phone when none exists', function (): void {
    $spy = new SpyTransport();
    $created = fixtureApiPayload('phone_single.json');
    $spy->queue(new ApiResponse(4, 0, []));
    $spy->queue(new ApiResponse(4, $created['count'], $created['values']));

    $phone = makeContactApi($spy)->updatePrimaryPhone(42, '+48987654321');

    expect($phone)->toBeInstanceOf(Phone::class)
        ->and($spy->calls[1]['action'])->toBe('create');

    /** @var array<string, mixed> $values */
    $values = $spy->calls[1]['params']['values'];
    expect($values['is_primary'])->toBeTrue();
});

it('updatePrimaryPhone omits phone_type when null is passed', function (): void {
    $spy = new SpyTransport();
    $forContact = fixtureApiPayload('phones_for_contact.json');
    $updated = fixtureApiPayload('phone_single.json');
    $spy->queue(new ApiResponse(4, $forContact['count'], $forContact['values']));
    $spy->queue(new ApiResponse(4, $updated['count'], $updated['values']));

    makeContactApi($spy)->updatePrimaryPhone(42, '+48999999999', null);

    expect($spy->calls[1]['params']['values'])->toBe(['phone' => '+48999999999']);
});

it('updatePrimaryAddress updates existing primary address', function (): void {
    $spy = new SpyTransport();
    $forContact = fixtureApiPayload('addresses_for_contact.json');
    $country = fixtureApiPayload('country_found.json');
    $updated = fixtureApiPayload('address_single.json');
    $spy->queue(new ApiResponse(4, $forContact['count'], $forContact['values']));
    $spy->queue(new ApiResponse(4, $country['count'], $country['values']));
    $spy->queue(new ApiResponse(4, $updated['count'], $updated['values']));

    $data = AddressData::fromArray([
        'street_address' => 'New St 10',
        'city' => 'Gdansk',
        'postal_code' => '80-001',
        'country' => 'PL',
    ]);

    $address = makeContactApi($spy)->updatePrimaryAddress(42, $data);

    expect($address)->toBeInstanceOf(Address::class)
        ->and($spy->calls[0]['action'])->toBe('get')
        ->and($spy->calls[2]['action'])->toBe('update');
});

it('updatePrimaryAddress creates primary address when none exists', function (): void {
    $spy = new SpyTransport();
    $country = fixtureApiPayload('country_found.json');
    $created = fixtureApiPayload('address_single.json');
    $spy->queue(new ApiResponse(4, 0, []));
    $spy->queue(new ApiResponse(4, $country['count'], $country['values']));
    $spy->queue(new ApiResponse(4, $created['count'], $created['values']));

    $data = AddressData::fromArray([
        'street_address' => 'New St 10',
        'city' => 'Gdansk',
        'postal_code' => '80-001',
        'country' => 'PL',
    ]);

    $address = makeContactApi($spy)->updatePrimaryAddress(42, $data);

    expect($address)->toBeInstanceOf(Address::class)
        ->and($spy->calls[1]['entity'])->toBe('Country')
        ->and($spy->calls[2]['action'])->toBe('create');

    /** @var array<string, mixed> $values */
    $values = $spy->calls[2]['params']['values'];
    expect($values['is_primary'])->toBeTrue();
});
