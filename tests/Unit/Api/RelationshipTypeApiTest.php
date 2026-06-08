<?php

declare(strict_types=1);

use Woduda\CiviCRM\Api\RelationshipTypeApi;
use Woduda\CiviCRM\Result\ApiResponse;

/**
 * @return list<array<string, mixed>>
 */
function relationshipTypeRows(): array
{
    return [
        [
            'id' => 5,
            'name_a_b' => 'Employee of',
            'name_b_a' => 'Employer of',
            'label_a_b' => 'Employee of',
            'label_b_a' => 'Employer of',
            'contact_type_a' => 'Individual',
            'contact_type_b' => 'Organization',
        ],
        [
            'id' => 6,
            'name_a_b' => 'Reports to',
            'name_b_a' => 'Manages',
            'label_a_b' => 'Reports to',
            'label_b_a' => 'Manages',
        ],
    ];
}

it('byName matches the forward name (A→B)', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 2, relationshipTypeRows()));

    $type = (new RelationshipTypeApi($spy))->byName('Employee of');

    expect($type?->id)->toBe(5)
        ->and($type?->labelBToA)->toBe('Employer of');
});

it('byName matches the reverse name (B→A) for the same record', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 2, relationshipTypeRows()));

    $type = (new RelationshipTypeApi($spy))->byName('Employer of');

    expect($type?->id)->toBe(5)
        ->and($type?->nameAToB)->toBe('Employee of');
});

it('byName returns null when no type matches either direction', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 2, relationshipTypeRows()));

    expect((new RelationshipTypeApi($spy))->byName('Friend of'))->toBeNull();
});

it('all() is memoized: a second call does not hit the transport', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 2, relationshipTypeRows()));

    $api = new RelationshipTypeApi($spy);
    $first = $api->all();
    $second = $api->all();

    expect($spy->calls)->toHaveCount(1)
        ->and($first->count)->toBe(2)
        ->and($second->count)->toBe(2);
});

it('byName reuses the all() cache across calls', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 2, relationshipTypeRows()));

    $api = new RelationshipTypeApi($spy);
    $api->byName('Employee of');
    $api->byName('Manages');

    expect($spy->calls)->toHaveCount(1);
});

it('ensureExists returns the existing type without creating', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [relationshipTypeRows()[0]]));

    $type = (new RelationshipTypeApi($spy))
        ->ensureExists('Employee of', 'Employer of', 'Employee of', 'Employer of');

    expect($type->id)->toBe(5)
        ->and($spy->calls)->toHaveCount(1)
        ->and($spy->calls[0]['entity'])->toBe('RelationshipType')
        ->and($spy->calls[0]['action'])->toBe('get')
        ->and($spy->calls[0]['params']['where'])->toBe([['name_a_b', '=', 'Employee of']]);
});

it('ensureExists is idempotent: a second call with the existing type does not create', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [relationshipTypeRows()[1]])); // first ensureExists → found
    $spy->queue(new ApiResponse(4, 1, [relationshipTypeRows()[1]])); // second ensureExists → found

    $api = new RelationshipTypeApi($spy);
    $api->ensureExists('Reports to', 'Manages', 'Reports to', 'Manages');
    $api->ensureExists('Reports to', 'Manages', 'Reports to', 'Manages');

    $actions = array_map(fn(array $c): string => $c['action'], $spy->calls);

    expect($actions)->toBe(['get', 'get'])
        ->and($actions)->not->toContain('create');
});

it('ensureExists creates the type when absent, passing all fields including contact types', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 0, []));                       // get → not found
    $spy->queue(new ApiResponse(4, 1, [relationshipTypeRows()[0]])); // create → new row

    $type = (new RelationshipTypeApi($spy))->ensureExists(
        'Employee of',
        'Employer of',
        'Employee of',
        'Employer of',
        'Individual',
        'Organization',
    );

    expect($type->id)->toBe(5)
        ->and($spy->calls)->toHaveCount(2)
        ->and($spy->calls[1]['action'])->toBe('create')
        ->and($spy->calls[1]['params']['values'])->toBe([
            'name_a_b' => 'Employee of',
            'name_b_a' => 'Employer of',
            'label_a_b' => 'Employee of',
            'label_b_a' => 'Employer of',
            'contact_type_a' => 'Individual',
            'contact_type_b' => 'Organization',
        ]);
});

it('ensureExists omits null contact types from the create values', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 0, []));
    $spy->queue(new ApiResponse(4, 1, [relationshipTypeRows()[1]]));

    (new RelationshipTypeApi($spy))->ensureExists('Reports to', 'Manages', 'Reports to', 'Manages');

    expect($spy->calls[1]['params']['values'])->toBe([
        'name_a_b' => 'Reports to',
        'name_b_a' => 'Manages',
        'label_a_b' => 'Reports to',
        'label_b_a' => 'Manages',
    ]);
});

it('ensureExists invalidates the cache so a later all() re-fetches', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, relationshipTypeRows()));   // all() → 2 rows
    $spy->queue(new ApiResponse(4, 0, []));                       // ensureExists get → not found
    $spy->queue(new ApiResponse(4, 1, [relationshipTypeRows()[1]])); // ensureExists create
    $spy->queue(new ApiResponse(4, 1, relationshipTypeRows()));   // all() again → re-fetch

    $api = new RelationshipTypeApi($spy);
    $api->all();
    $api->ensureExists('Friend of', 'Friend of', 'Friend of', 'Friend of');
    $api->all();

    $actions = array_map(fn(array $c): string => $c['action'], $spy->calls);

    expect($actions)->toBe(['get', 'get', 'create', 'get']);
});

it('getFields sends entity=RelationshipType and action=getfields', function (): void {
    $spy = new SpyTransport();

    (new RelationshipTypeApi($spy))->getFields();

    expect($spy->calls[0]['entity'])->toBe('RelationshipType')
        ->and($spy->calls[0]['action'])->toBe('getfields');
});

it('getActions sends entity=RelationshipType and action=getactions', function (): void {
    $spy = new SpyTransport();

    (new RelationshipTypeApi($spy))->getActions();

    expect($spy->calls[0]['entity'])->toBe('RelationshipType')
        ->and($spy->calls[0]['action'])->toBe('getactions');
});
