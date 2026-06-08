<?php

declare(strict_types=1);

use Woduda\CiviCRM\Entity\Relationship;

it('hydrates a relationship, exposing both directional labels', function (): void {
    $row = fixtureFirstRow('relationship-single.json');
    $rel = Relationship::fromArray($row);

    expect($rel->id)->toBe(101)
        ->and($rel->contactIdA)->toBe(42)
        ->and($rel->contactIdB)->toBe(7)
        ->and($rel->relationshipTypeId)->toBe(5)
        ->and($rel->startDate?->format('Y-m-d'))->toBe('2024-01-15')
        ->and($rel->endDate?->format('Y-m-d'))->toBe('2026-01-01')
        ->and($rel->isActive)->toBeTrue()
        ->and($rel->description)->toBe('Primary reporting line')
        ->and($rel->labelAToB)->toBe('Reports to')
        ->and($rel->labelBToA)->toBe('Manages')
        ->and($rel->rawData)->toBe($row);
});

it('parses a datetime-style start date', function (): void {
    $rel = Relationship::fromArray([
        'id' => 1,
        'start_date' => '2024-01-15 10:30:00',
    ]);

    expect($rel->startDate?->format('Y-m-d H:i:s'))->toBe('2024-01-15 10:30:00');
});

it('leaves dates null when absent, empty, or unparsable', function (mixed $value): void {
    $rel = Relationship::fromArray(['id' => 1, 'start_date' => $value, 'end_date' => $value]);

    expect($rel->startDate)->toBeNull()
        ->and($rel->endDate)->toBeNull();
})->with([
    'empty string' => [''],
    'non-string' => [12345],
    'garbage' => ['not-a-date'],
]);

it('leaves dates null when the keys are missing entirely', function (): void {
    $rel = Relationship::fromArray(['id' => 1]);

    expect($rel->startDate)->toBeNull()
        ->and($rel->endDate)->toBeNull();
});

it('leaves the directional labels null when not joined into the response', function (): void {
    $rel = Relationship::fromArray([
        'id' => 1,
        'contact_id_a' => 42,
        'contact_id_b' => 7,
        'relationship_type_id' => 5,
    ]);

    expect($rel->labelAToB)->toBeNull()
        ->and($rel->labelBToA)->toBeNull()
        ->and($rel->description)->toBeNull();
});

it('coerces is_active from APIv4 truthy representations', function (mixed $value, bool $expected): void {
    $rel = Relationship::fromArray(['id' => 1, 'is_active' => $value]);

    expect($rel->isActive)->toBe($expected);
})->with([
    'bool true' => [true, true],
    'int 1' => [1, true],
    'string 1' => ['1', true],
    'int 0' => [0, false],
    'bool false' => [false, false],
]);

it('round-trips dates through toArray as Y-m-d', function (): void {
    $rel = Relationship::fromArray(fixtureFirstRow('relationship-single.json'));
    $exported = $rel->toArray();

    expect($exported)->toMatchArray([
        'id' => 101,
        'contact_id_a' => 42,
        'contact_id_b' => 7,
        'relationship_type_id' => 5,
        'is_active' => true,
        'start_date' => '2024-01-15',
        'end_date' => '2026-01-01',
        'description' => 'Primary reporting line',
        'relationship_type_id.label_a_b' => 'Reports to',
        'relationship_type_id.label_b_a' => 'Manages',
    ]);

    $roundTrip = Relationship::fromArray($exported);

    expect($roundTrip->startDate?->format('Y-m-d'))->toBe('2024-01-15')
        ->and($roundTrip->endDate?->format('Y-m-d'))->toBe('2026-01-01');
});

it('omits null optional fields from toArray', function (): void {
    $exported = Relationship::fromArray([
        'id' => 1,
        'contact_id_a' => 42,
        'contact_id_b' => 7,
        'relationship_type_id' => 5,
    ])->toArray();

    expect($exported)->not->toHaveKey('start_date')
        ->and($exported)->not->toHaveKey('end_date')
        ->and($exported)->not->toHaveKey('description')
        ->and($exported)->not->toHaveKey('relationship_type_id.label_a_b')
        ->and($exported)->not->toHaveKey('relationship_type_id.label_b_a');
});
