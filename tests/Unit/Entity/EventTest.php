<?php

declare(strict_types=1);

use Woduda\CiviCRM\Entity\Event;

// ---------------------------------------------------------------------------
// fromArray — full hydration
// ---------------------------------------------------------------------------

it('fromArray hydrates all fields from fixture row', function (): void {
    $row = fixtureFirstRow('event-single.json');
    $event = Event::fromArray($row);

    expect($event->id)->toBe(10)
        ->and($event->title)->toBe('Annual Gala 2026')
        ->and($event->summary)->toBe('Our flagship annual fundraising gala.')
        ->and($event->description)->toBe('Join us for an evening of celebration and giving.')
        ->and($event->startDate->format('Y-m-d H:i:s'))->toBe('2026-09-15 18:00:00')
        ->and($event->endDate?->format('Y-m-d H:i:s'))->toBe('2026-09-15 23:00:00')
        ->and($event->eventTypeId)->toBe(1)
        ->and($event->isActive)->toBeTrue()
        ->and($event->isPublic)->toBeTrue()
        ->and($event->maxParticipants)->toBe(50)
        ->and($event->defaultRoleId)->toBe(1);
});

it('fromArray preserves rawData', function (): void {
    $row = fixtureFirstRow('event-single.json');
    $event = Event::fromArray($row);

    expect($event->rawData)->toBe($row);
});

// ---------------------------------------------------------------------------
// fromArray — missing optional fields
// ---------------------------------------------------------------------------

it('fromArray defaults optional fields to null when missing', function (): void {
    $event = Event::fromArray([
        'id' => 1,
        'title' => 'Minimal Event',
        'start_date' => '2026-07-01 09:00:00',
        'event_type_id' => 2,
        'is_active' => true,
        'is_public' => false,
    ]);

    expect($event->summary)->toBeNull()
        ->and($event->description)->toBeNull()
        ->and($event->endDate)->toBeNull()
        ->and($event->maxParticipants)->toBeNull()
        ->and($event->defaultRoleId)->toBeNull();
});

it('fromArray defaults to safe values when required fields are missing', function (): void {
    $event = Event::fromArray([]);

    expect($event->id)->toBe(0)
        ->and($event->title)->toBe('')
        ->and($event->eventTypeId)->toBe(0)
        ->and($event->isActive)->toBeFalse()
        ->and($event->isPublic)->toBeFalse()
        ->and($event->startDate->format('Y-m-d'))->toBe('1970-01-01');
});

it('fromArray falls back to epoch when start_date is invalid', function (): void {
    $event = Event::fromArray(['start_date' => 'not-a-date']);

    expect($event->startDate->format('Y-m-d'))->toBe('1970-01-01');
});

it('fromArray sets endDate to null when end_date is invalid', function (): void {
    $event = Event::fromArray(['end_date' => 'not-a-date']);

    expect($event->endDate)->toBeNull();
});

// ---------------------------------------------------------------------------
// toArray
// ---------------------------------------------------------------------------

it('toArray includes all required fields', function (): void {
    $row = fixtureFirstRow('event-single.json');
    $event = Event::fromArray($row);
    $data = $event->toArray();

    expect($data)->toHaveKey('id', 10)
        ->and($data)->toHaveKey('title', 'Annual Gala 2026')
        ->and($data)->toHaveKey('start_date', '2026-09-15 18:00:00')
        ->and($data)->toHaveKey('event_type_id', 1)
        ->and($data)->toHaveKey('is_active', true)
        ->and($data)->toHaveKey('is_public', true);
});

it('toArray omits null optional fields', function (): void {
    $event = Event::fromArray([
        'id' => 1,
        'title' => 'Minimal',
        'start_date' => '2026-07-01 09:00:00',
        'event_type_id' => 1,
        'is_active' => true,
        'is_public' => false,
    ]);
    $data = $event->toArray();

    expect($data)->not->toHaveKey('summary')
        ->and($data)->not->toHaveKey('description')
        ->and($data)->not->toHaveKey('end_date')
        ->and($data)->not->toHaveKey('max_participants')
        ->and($data)->not->toHaveKey('default_role_id');
});

it('toArray round-trips all present fields', function (): void {
    $row = fixtureFirstRow('event-single.json');
    $event = Event::fromArray($row);
    $data = $event->toArray();

    expect($data['id'])->toBe($event->id)
        ->and($data['title'])->toBe($event->title)
        ->and($data['start_date'])->toBe($event->startDate->format('Y-m-d H:i:s'))
        ->and($data['end_date'])->toBe($event->endDate?->format('Y-m-d H:i:s'))
        ->and($data['max_participants'])->toBe($event->maxParticipants);
});
