<?php

declare(strict_types=1);

use Woduda\CiviCRM\Api\RelationshipApi;
use Woduda\CiviCRM\Entity\Relationship;
use Woduda\CiviCRM\Query\Operator;
use Woduda\CiviCRM\Result\ApiResponse;
use Woduda\CiviCRM\Result\Result;

/**
 * @return list<array<string, mixed>>
 */
function relationshipTypeCatalog(): array
{
    return [[
        'id' => 6,
        'name_a_b' => 'Reports to',
        'name_b_a' => 'Manages',
        'label_a_b' => 'Reports to',
        'label_b_a' => 'Manages',
    ]];
}

/**
 * @return array<string, mixed>
 */
function createdRelationshipRow(): array
{
    return [
        'id' => 101,
        'contact_id_a' => 42,
        'contact_id_b' => 7,
        'relationship_type_id' => 6,
    ];
}

it('create with an int type id sends a single Relationship.create request', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [createdRelationshipRow()]));

    $rel = (new RelationshipApi($spy))->create(42, 7, 6);

    expect($rel)->toBeInstanceOf(Relationship::class)
        ->and($rel->id)->toBe(101)
        ->and($spy->calls)->toHaveCount(1)
        ->and($spy->calls[0]['entity'])->toBe('Relationship')
        ->and($spy->calls[0]['action'])->toBe('create')
        ->and($spy->calls[0]['params']['values'])->toBe([
            'contact_id_a' => 42,
            'contact_id_b' => 7,
            'relationship_type_id' => 6,
        ]);
});

it('create with a string type name resolves it and yields an identical create request', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, relationshipTypeCatalog())); // RelationshipType.get (byName)
    $spy->queue(new ApiResponse(4, 1, [createdRelationshipRow()])); // Relationship.create

    (new RelationshipApi($spy))->create(42, 7, 'Reports to');

    expect($spy->calls)->toHaveCount(2)
        ->and($spy->calls[0]['entity'])->toBe('RelationshipType')
        ->and($spy->calls[0]['action'])->toBe('get')
        ->and($spy->calls[1]['entity'])->toBe('Relationship')
        ->and($spy->calls[1]['action'])->toBe('create')
        // identical to the int-id path's create values
        ->and($spy->calls[1]['params']['values'])->toBe([
            'contact_id_a' => 42,
            'contact_id_b' => 7,
            'relationship_type_id' => 6,
        ]);
});

it('create resolves the reverse type name to the same id', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, relationshipTypeCatalog()));
    $spy->queue(new ApiResponse(4, 1, [createdRelationshipRow()]));

    (new RelationshipApi($spy))->create(7, 42, 'Manages');

    expect($spy->calls[1]['params']['values'])->toBe([
        'contact_id_a' => 7,
        'contact_id_b' => 42,
        'relationship_type_id' => 6,
    ]);
});

it('create formats start_date as Y-m-d and merges extra fields', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [createdRelationshipRow()]));

    (new RelationshipApi($spy))->create(
        42,
        7,
        6,
        new DateTimeImmutable('2025-01-01 09:00:00'),
        ['description' => 'New hire'],
    );

    expect($spy->calls[0]['params']['values'])->toBe([
        'contact_id_a' => 42,
        'contact_id_b' => 7,
        'relationship_type_id' => 6,
        'start_date' => '2025-01-01',
        'description' => 'New hire',
    ]);
});

it('create falls back to id 0 when the string type cannot be resolved', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 0, []));                          // byName → no types
    $spy->queue(new ApiResponse(4, 1, [createdRelationshipRow()]));  // create

    (new RelationshipApi($spy))->create(42, 7, 'Unknown');

    expect($spy->calls[1]['params']['values'])->toBe([
        'contact_id_a' => 42,
        'contact_id_b' => 7,
        'relationship_type_id' => 0,
    ]);
});

it('terminate sets end_date (Y-m-d) and is_active=false on the given relationship', function (): void {
    $spy = new SpyTransport();

    (new RelationshipApi($spy))->terminate(101, new DateTimeImmutable('2026-01-01'));

    expect($spy->calls)->toHaveCount(1)
        ->and($spy->calls[0]['entity'])->toBe('Relationship')
        ->and($spy->calls[0]['action'])->toBe('update')
        ->and($spy->calls[0]['params']['values'])->toBe([
            'end_date' => '2026-01-01',
            'is_active' => false,
        ])
        ->and($spy->calls[0]['params']['where'])->toBe([['id', '=', 101]]);
});

it('forContact returns relationships where the contact is side A OR side B', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [fixtureFirstRow('relationship-single.json')]));

    $result = (new RelationshipApi($spy))->forContact(42);

    expect($result)->toBeInstanceOf(Result::class)
        ->and($result->first())->toBeInstanceOf(Relationship::class)
        ->and($spy->calls[0]['action'])->toBe('get')
        ->and($spy->calls[0]['params']['where'])->toBe([
            ['OR', [['contact_id_a', '=', 42], ['contact_id_b', '=', 42]]],
            ['is_active', '=', true],
        ]);
});

it('forContact joins both directional labels into the select', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [fixtureFirstRow('relationship-single.json')]));

    (new RelationshipApi($spy))->forContact(42);

    expect($spy->calls[0]['params']['select'])->toBe([
        '*',
        'relationship_type_id.label_a_b',
        'relationship_type_id.label_b_a',
    ]);
});

it('forContact with activeOnly=false omits the is_active clause', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 0, []));

    (new RelationshipApi($spy))->forContact(42, activeOnly: false);

    expect($spy->calls[0]['params']['where'])->toBe([
        ['OR', [['contact_id_a', '=', 42], ['contact_id_b', '=', 42]]],
    ]);
});

it('forContact hydrates both directional labels onto the typed result', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [fixtureFirstRow('relationship-single.json')]));

    $rel = (new RelationshipApi($spy))->forContact(42)->first();

    expect($rel?->labelAToB)->toBe('Reports to')
        ->and($rel?->labelBToA)->toBe('Manages');
});

it('ofType produces a GetQuery filtered on the resolved relationship_type_id', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, relationshipTypeCatalog())); // byName resolution

    $query = (new RelationshipApi($spy))->ofType('Reports to');

    expect($query->toParams()['where'])->toBe([['relationship_type_id', '=', 6]]);
});

it('ofType is refinable and runnable through get()', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, relationshipTypeCatalog())); // byName resolution
    $spy->queue(new ApiResponse(4, 1, [fixtureFirstRow('relationship-single.json')])); // get

    $api = new RelationshipApi($spy);
    $result = $api->get($api->ofType('Reports to')->where('start_date', Operator::GreaterThan, '2024-01-01'));

    expect($result->first())->toBeInstanceOf(Relationship::class)
        ->and($spy->calls[1]['params']['where'])->toBe([
            ['relationship_type_id', '=', 6],
            ['start_date', '>', '2024-01-01'],
        ]);
});

it('getFields sends entity=Relationship and action=getfields', function (): void {
    $spy = new SpyTransport();

    (new RelationshipApi($spy))->getFields();

    expect($spy->calls[0]['entity'])->toBe('Relationship')
        ->and($spy->calls[0]['action'])->toBe('getfields');
});

it('getActions sends entity=Relationship and action=getactions', function (): void {
    $spy = new SpyTransport();

    (new RelationshipApi($spy))->getActions();

    expect($spy->calls[0]['entity'])->toBe('Relationship')
        ->and($spy->calls[0]['action'])->toBe('getactions');
});
