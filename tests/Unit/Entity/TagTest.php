<?php

declare(strict_types=1);

use Woduda\CiviCRM\Entity\Tag;

it('hydrates a tag from a full API row', function (): void {
    $row = fixtureFirstRow('tag-found.json');
    $tag = Tag::fromArray($row);

    expect($tag->id)->toBe(7)
        ->and($tag->name)->toBe('VIP')
        ->and($tag->usedFor)->toBe('civicrm_contact')
        ->and($tag->rawData)->toBe($row);
});

it('tolerates missing optional fields without throwing', function (): void {
    $tag = Tag::fromArray(['name' => 'Donor']);

    expect($tag->id)->toBeNull()
        ->and($tag->name)->toBe('Donor')
        ->and($tag->usedFor)->toBeNull();
});

it('returns null for name when the value is not a string', function (): void {
    $tag = Tag::fromArray(['id' => 7, 'name' => 123]);

    expect($tag->name)->toBeNull();
});

it('round-trips mapped fields through toArray', function (): void {
    $tag = Tag::fromArray(fixtureFirstRow('tag-found.json'));
    $exported = $tag->toArray();

    expect($exported)->toMatchArray([
        'id' => 7,
        'name' => 'VIP',
        'used_for' => 'civicrm_contact',
    ]);

    $roundTrip = Tag::fromArray($exported);

    expect($roundTrip->id)->toBe($tag->id)
        ->and($roundTrip->name)->toBe($tag->name)
        ->and($roundTrip->usedFor)->toBe($tag->usedFor);
});
