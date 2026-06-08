<?php

declare(strict_types=1);

use Woduda\CiviCRM\Entity\RelationshipType;

it('hydrates a relationship type from a full API row, keeping both directions', function (): void {
    $row = fixtureFirstRow('relationship-type-single.json');
    $type = RelationshipType::fromArray($row);

    expect($type->id)->toBe(5)
        ->and($type->nameAToB)->toBe('Employee of')
        ->and($type->nameBToA)->toBe('Employer of')
        ->and($type->labelAToB)->toBe('Employee of')
        ->and($type->labelBToA)->toBe('Employer of')
        ->and($type->contactTypeA)->toBe('Individual')
        ->and($type->contactTypeB)->toBe('Organization')
        ->and($type->rawData)->toBe($row);
});

it('treats absent contact types as null (any contact type allowed)', function (): void {
    $type = RelationshipType::fromArray([
        'id' => 9,
        'name_a_b' => 'Spouse of',
        'name_b_a' => 'Spouse of',
        'label_a_b' => 'Spouse of',
        'label_b_a' => 'Spouse of',
    ]);

    expect($type->contactTypeA)->toBeNull()
        ->and($type->contactTypeB)->toBeNull();
});

it('coerces a non-integer id to 0 and missing names to empty strings', function (): void {
    $type = RelationshipType::fromArray(['id' => 'nope']);

    expect($type->id)->toBe(0)
        ->and($type->nameAToB)->toBe('')
        ->and($type->nameBToA)->toBe('')
        ->and($type->labelAToB)->toBe('')
        ->and($type->labelBToA)->toBe('');
});

it('round-trips mapped fields through toArray', function (): void {
    $type = RelationshipType::fromArray(fixtureFirstRow('relationship-type-single.json'));
    $exported = $type->toArray();

    expect($exported)->toMatchArray([
        'id' => 5,
        'name_a_b' => 'Employee of',
        'name_b_a' => 'Employer of',
        'label_a_b' => 'Employee of',
        'label_b_a' => 'Employer of',
        'contact_type_a' => 'Individual',
        'contact_type_b' => 'Organization',
    ]);

    $roundTrip = RelationshipType::fromArray($exported);

    expect($roundTrip->nameAToB)->toBe($type->nameAToB)
        ->and($roundTrip->nameBToA)->toBe($type->nameBToA)
        ->and($roundTrip->contactTypeA)->toBe($type->contactTypeA)
        ->and($roundTrip->contactTypeB)->toBe($type->contactTypeB);
});

it('omits null contact types from toArray', function (): void {
    $exported = RelationshipType::fromArray([
        'id' => 9,
        'name_a_b' => 'Spouse of',
        'name_b_a' => 'Spouse of',
        'label_a_b' => 'Spouse of',
        'label_b_a' => 'Spouse of',
    ])->toArray();

    expect($exported)->not->toHaveKey('contact_type_a')
        ->and($exported)->not->toHaveKey('contact_type_b');
});
