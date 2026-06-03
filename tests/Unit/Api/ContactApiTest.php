<?php

declare(strict_types=1);

use Woduda\CiviCRM\Api\ContactApi;
use Woduda\CiviCRM\Api\CustomFieldResolver;
use Woduda\CiviCRM\Exception\ValidationException;
use Woduda\CiviCRM\Query\GetQuery;
use Woduda\CiviCRM\Query\Operator;
use Woduda\CiviCRM\Result\ApiResponse;

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

it('get returns the values array from the transport response', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 42, 'display_name' => 'Jane Doe']]));

    $result = makeContactApi($spy)->get(GetQuery::new());

    expect($result)->toBe([['id' => 42, 'display_name' => 'Jane Doe']]);
});

// ---------------------------------------------------------------------------
// getById
// ---------------------------------------------------------------------------

it('getById returns the first matching contact', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 42, 'display_name' => 'Jane Doe']]));

    $contact = makeContactApi($spy)->getById(42);

    expect($contact)->toBe(['id' => 42, 'display_name' => 'Jane Doe'])
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

it('create returns the values array from the transport response', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 99, 'first_name' => 'Jane']]));

    $result = makeContactApi($spy)->create(['first_name' => 'Jane']);

    expect($result)->toBe([['id' => 99, 'first_name' => 'Jane']]);
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

// ---------------------------------------------------------------------------
// withTags
// ---------------------------------------------------------------------------

it('withTags does nothing when the tag list is empty', function (): void {
    $spy = new SpyTransport();

    makeContactApi($spy)->withTags(42, []);

    expect($spy->calls)->toHaveCount(0);
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
