<?php

declare(strict_types=1);

use Woduda\CiviCRM\Entity\Participant;
use Woduda\CiviCRM\Entity\ParticipantStatus;

// ---------------------------------------------------------------------------
// fromArray — full hydration
// ---------------------------------------------------------------------------

it('fromArray hydrates all fields from fixture row', function (): void {
    $row = fixtureFirstRow('participant-single.json');
    $participant = Participant::fromArray($row);

    expect($participant->id)->toBe(1)
        ->and($participant->contactId)->toBe(42)
        ->and($participant->eventId)->toBe(10)
        ->and($participant->status)->toBe(ParticipantStatus::Registered)
        ->and($participant->roleId)->toBe(1)
        ->and($participant->registerDate?->format('Y-m-d H:i:s'))->toBe('2026-06-01 10:00:00')
        ->and($participant->source)->toBe('Website registration');
});

it('fromArray preserves rawData', function (): void {
    $row = fixtureFirstRow('participant-single.json');
    $participant = Participant::fromArray($row);

    expect($participant->rawData)->toBe($row);
});

// ---------------------------------------------------------------------------
// fromArray — status hydration paths
// ---------------------------------------------------------------------------

it('fromArray hydrates status from status_id:name string field', function (): void {
    $participant = Participant::fromArray(['status_id:name' => 'Attended']);

    expect($participant->status)->toBe(ParticipantStatus::Attended);
});

it('fromArray hydrates status from status_id integer field when name is absent', function (): void {
    $participant = Participant::fromArray(['status_id' => 5]);

    expect($participant->status)->toBe(ParticipantStatus::PendingPayLater);
});

it('fromArray falls back to Registered when both status fields are missing', function (): void {
    $participant = Participant::fromArray([]);

    expect($participant->status)->toBe(ParticipantStatus::Registered);
});

it('fromArray falls back to Registered for unknown status name', function (): void {
    $participant = Participant::fromArray(['status_id:name' => 'UnknownCustomStatus']);

    expect($participant->status)->toBe(ParticipantStatus::Registered);
});

it('fromArray falls back to Registered for unknown status id', function (): void {
    $participant = Participant::fromArray(['status_id' => 999]);

    expect($participant->status)->toBe(ParticipantStatus::Registered);
});

// ---------------------------------------------------------------------------
// fromArray — optional fields
// ---------------------------------------------------------------------------

it('fromArray sets registerDate to null when register_date is missing', function (): void {
    $participant = Participant::fromArray(['id' => 1, 'contact_id' => 42, 'event_id' => 10]);

    expect($participant->registerDate)->toBeNull();
});

it('fromArray sets registerDate to null when register_date is invalid', function (): void {
    $participant = Participant::fromArray(['register_date' => 'not-a-date']);

    expect($participant->registerDate)->toBeNull();
});

it('fromArray sets source to null when missing', function (): void {
    $participant = Participant::fromArray([]);

    expect($participant->source)->toBeNull();
});

it('fromArray sets roleId to null when missing', function (): void {
    $participant = Participant::fromArray([]);

    expect($participant->roleId)->toBeNull();
});

// ---------------------------------------------------------------------------
// toArray
// ---------------------------------------------------------------------------

it('toArray includes required fields', function (): void {
    $row = fixtureFirstRow('participant-single.json');
    $participant = Participant::fromArray($row);
    $data = $participant->toArray();

    expect($data)->toHaveKey('id', 1)
        ->and($data)->toHaveKey('contact_id', 42)
        ->and($data)->toHaveKey('event_id', 10)
        ->and($data)->toHaveKey('status_id:name', 'Registered');
});

it('toArray omits null optional fields', function (): void {
    $participant = Participant::fromArray([
        'id' => 1,
        'contact_id' => 42,
        'event_id' => 10,
    ]);
    $data = $participant->toArray();

    expect($data)->not->toHaveKey('role_id')
        ->and($data)->not->toHaveKey('register_date')
        ->and($data)->not->toHaveKey('source');
});

it('toArray round-trips all present fields', function (): void {
    $row = fixtureFirstRow('participant-single.json');
    $participant = Participant::fromArray($row);
    $data = $participant->toArray();

    expect($data['id'])->toBe($participant->id)
        ->and($data['contact_id'])->toBe($participant->contactId)
        ->and($data['event_id'])->toBe($participant->eventId)
        ->and($data['status_id:name'])->toBe($participant->status->value)
        ->and($data['register_date'])->toBe($participant->registerDate?->format('Y-m-d H:i:s'));
});
