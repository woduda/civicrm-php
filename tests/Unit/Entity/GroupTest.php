<?php

declare(strict_types=1);

use Woduda\CiviCRM\Entity\Group;

it('hydrates a group from a full API row', function (): void {
    $row = fixtureFirstRow('group-found.json');
    $group = Group::fromArray($row);

    expect($group->id)->toBe(3)
        ->and($group->title)->toBe('Newsletter')
        ->and($group->groupType)->toBe('Mailing List')
        ->and($group->rawData)->toBe($row);
});

it('tolerates missing optional fields without throwing', function (): void {
    $group = Group::fromArray(['title' => 'Volunteers']);

    expect($group->id)->toBeNull()
        ->and($group->title)->toBe('Volunteers')
        ->and($group->groupType)->toBeNull();
});

it('returns null for title when the value is not a string', function (): void {
    $group = Group::fromArray(['id' => 3, 'title' => 456]);

    expect($group->title)->toBeNull();
});

it('round-trips mapped fields through toArray', function (): void {
    $group = Group::fromArray(fixtureFirstRow('group-found.json'));
    $exported = $group->toArray();

    expect($exported)->toMatchArray([
        'id' => 3,
        'title' => 'Newsletter',
        'group_type' => 'Mailing List',
    ]);

    $roundTrip = Group::fromArray($exported);

    expect($roundTrip->id)->toBe($group->id)
        ->and($roundTrip->title)->toBe($group->title)
        ->and($roundTrip->groupType)->toBe($group->groupType);
});
