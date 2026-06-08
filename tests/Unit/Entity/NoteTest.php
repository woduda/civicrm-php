<?php

declare(strict_types=1);

use Woduda\CiviCRM\Entity\Note;

it('hydrates a note from a full API row', function (): void {
    $row = fixtureFirstRow('note-single.json');
    $note = Note::fromArray($row);

    expect($note->id)->toBe(17)
        ->and($note->entityTable)->toBe('civicrm_contact')
        ->and($note->entityId)->toBe(42)
        ->and($note->subject)->toBe('Follow-up')
        ->and($note->note)->toBe('Called to confirm appointment.')
        ->and($note->privacy)->toBe('public')
        ->and($note->modifiedDate->format('Y-m-d H:i:s'))->toBe('2026-06-08 10:00:00')
        ->and($note->contactIdCreator)->toBe(1)
        ->and($note->rawData)->toBe($row);
});

it('tolerates missing optional fields without throwing', function (): void {
    $note = Note::fromArray(['id' => 5, 'entity_id' => 10]);

    expect($note->id)->toBe(5)
        ->and($note->entityId)->toBe(10)
        ->and($note->subject)->toBeNull()
        ->and($note->privacy)->toBeNull()
        ->and($note->contactIdCreator)->toBeNull()
        ->and($note->note)->toBe('')
        ->and($note->entityTable)->toBe('');
});

it('falls back to epoch when modified_date is missing', function (): void {
    $note = Note::fromArray(['id' => 1]);

    expect($note->modifiedDate->format('Y-m-d'))->toBe('1970-01-01');
});

it('falls back to epoch when modified_date is an invalid format', function (): void {
    $note = Note::fromArray(['id' => 1, 'modified_date' => 'not-a-date']);

    expect($note->modifiedDate->format('Y-m-d'))->toBe('1970-01-01');
});

it('round-trips mapped fields through toArray', function (): void {
    $row = fixtureFirstRow('note-single.json');
    $note = Note::fromArray($row);
    $exported = $note->toArray();

    expect($exported)->toMatchArray([
        'id' => 17,
        'entity_table' => 'civicrm_contact',
        'entity_id' => 42,
        'subject' => 'Follow-up',
        'note' => 'Called to confirm appointment.',
        'privacy' => 'public',
        'modified_date' => '2026-06-08 10:00:00',
        'contact_id' => 1,
    ]);

    $roundTrip = Note::fromArray($exported);

    expect($roundTrip->id)->toBe($note->id)
        ->and($roundTrip->entityTable)->toBe($note->entityTable)
        ->and($roundTrip->entityId)->toBe($note->entityId)
        ->and($roundTrip->subject)->toBe($note->subject)
        ->and($roundTrip->note)->toBe($note->note)
        ->and($roundTrip->privacy)->toBe($note->privacy)
        ->and($roundTrip->modifiedDate->format('Y-m-d H:i:s'))->toBe($note->modifiedDate->format('Y-m-d H:i:s'))
        ->and($roundTrip->contactIdCreator)->toBe($note->contactIdCreator);
});

it('omits null optional fields from toArray', function (): void {
    $note = Note::fromArray(['id' => 3, 'entity_table' => 'civicrm_contact', 'entity_id' => 7, 'note' => 'Test']);
    $exported = $note->toArray();

    expect($exported)->not->toHaveKey('subject')
        ->and($exported)->not->toHaveKey('privacy')
        ->and($exported)->not->toHaveKey('contact_id');
});

it('defaults id to 0 when id key is absent', function (): void {
    $note = Note::fromArray(['entity_id' => 5]);

    expect($note->id)->toBe(0);
});

it('defaults id to 0 when id is not an integer', function (): void {
    $note = Note::fromArray(['id' => 'bad']);

    expect($note->id)->toBe(0);
});

it('defaults entityId to 0 when entity_id key is absent', function (): void {
    $note = Note::fromArray(['id' => 1]);

    expect($note->entityId)->toBe(0);
});

it('defaults entityId to 0 when entity_id is not an integer', function (): void {
    $note = Note::fromArray(['id' => 1, 'entity_id' => 'bad']);

    expect($note->entityId)->toBe(0);
});
