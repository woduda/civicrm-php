<?php

declare(strict_types=1);

use Woduda\CiviCRM\Api\EventApi;
use Woduda\CiviCRM\Contract\ClockInterface;
use Woduda\CiviCRM\Entity\Event;
use Woduda\CiviCRM\Entity\ParticipantStatus;
use Woduda\CiviCRM\Query\GetQuery;
use Woduda\CiviCRM\Query\Operator;
use Woduda\CiviCRM\Result\ApiResponse;
use Woduda\CiviCRM\Result\Result;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeEventApi(SpyTransport $spy, ?ClockInterface $clock = null): EventApi
{
    $clock ??= new class implements ClockInterface {
        public function now(): DateTimeImmutable
        {
            return new DateTimeImmutable('2026-06-01 00:00:00');
        }
    };

    return new EventApi($spy, $clock);
}

function singleEventResponse(): ApiResponse
{
    return new ApiResponse(4, 1, [fixtureFirstRow('event-single.json')]);
}

function rowCountResponse(int $count): ApiResponse
{
    return new ApiResponse(4, 1, [['row_count' => $count]]);
}

/**
 * Narrows params['where'] to list<list<mixed>>.
 *
 * @return list<list<mixed>>
 */
function callEventWhere(SpyTransport $spy, int $callIndex): array
{
    $where = $spy->calls[$callIndex]['params']['where'] ?? [];

    if (! is_array($where)) {
        return [];
    }

    /** @var list<list<mixed>> */
    return array_values(array_filter($where, is_array(...)));
}

// ---------------------------------------------------------------------------
// get
// ---------------------------------------------------------------------------

it('get sends entity=Event, action=get with compiled query params', function (): void {
    $spy = new SpyTransport();
    $query = GetQuery::new()->where('event_type_id', Operator::Equals, 1)->limit(5);

    makeEventApi($spy)->get($query);

    expect($spy->calls[0]['entity'])->toBe('Event')
        ->and($spy->calls[0]['action'])->toBe('get')
        ->and($spy->calls[0]['params'])->toBe($query->toParams());
});

it('get returns a Result of Event DTOs', function (): void {
    $spy = new SpyTransport();
    $spy->queue(singleEventResponse());

    $result = makeEventApi($spy)->get(GetQuery::new());

    expect($result)->toBeInstanceOf(Result::class)
        ->and($result->count())->toBe(1)
        ->and($result->first())->toBeInstanceOf(Event::class)
        ->and($result->first()?->id)->toBe(10);
});

// ---------------------------------------------------------------------------
// getById
// ---------------------------------------------------------------------------

it('getById sends where id=X and limit=1', function (): void {
    $spy = new SpyTransport();

    makeEventApi($spy)->getById(10);

    expect($spy->calls[0]['params']['where'])->toBe([['id', '=', 10]])
        ->and($spy->calls[0]['params']['limit'])->toBe(1);
});

it('getById returns a hydrated Event when found', function (): void {
    $spy = new SpyTransport();
    $spy->queue(singleEventResponse());

    $event = makeEventApi($spy)->getById(10);

    expect($event)->toBeInstanceOf(Event::class)
        ->and($event?->id)->toBe(10);
});

it('getById returns null when not found', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 0, []));

    $event = makeEventApi($spy)->getById(999);

    expect($event)->toBeNull();
});

// ---------------------------------------------------------------------------
// findByTitle
// ---------------------------------------------------------------------------

it('findByTitle sends where title=X and limit=1', function (): void {
    $spy = new SpyTransport();

    makeEventApi($spy)->findByTitle('Annual Gala 2026');

    expect($spy->calls[0]['params']['where'])->toBe([['title', '=', 'Annual Gala 2026']])
        ->and($spy->calls[0]['params']['limit'])->toBe(1);
});

it('findByTitle returns a hydrated Event when found', function (): void {
    $spy = new SpyTransport();
    $spy->queue(singleEventResponse());

    $event = makeEventApi($spy)->findByTitle('Annual Gala 2026');

    expect($event)->toBeInstanceOf(Event::class)
        ->and($event?->title)->toBe('Annual Gala 2026');
});

it('findByTitle returns null when not found', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 0, []));

    $event = makeEventApi($spy)->findByTitle('Non-existent Event');

    expect($event)->toBeNull();
});

// ---------------------------------------------------------------------------
// upcoming
// ---------------------------------------------------------------------------

it('upcoming filters by start_date > clock time and is_active=true', function (): void {
    $spy = new SpyTransport();

    makeEventApi($spy)->upcoming();

    $where = $spy->calls[0]['params']['where'];
    expect($where)->toContain(['start_date', '>', '2026-06-01 00:00:00'])
        ->and($where)->toContain(['is_active', '=', true]);
});

it('upcoming orders by start_date ASC', function (): void {
    $spy = new SpyTransport();

    makeEventApi($spy)->upcoming();

    expect($spy->calls[0]['params']['orderBy'])->toBe(['start_date' => 'ASC']);
});

it('upcoming applies the limit parameter', function (): void {
    $spy = new SpyTransport();

    makeEventApi($spy)->upcoming(5);

    expect($spy->calls[0]['params']['limit'])->toBe(5);
});

it('upcoming defaults to limit=10', function (): void {
    $spy = new SpyTransport();

    makeEventApi($spy)->upcoming();

    expect($spy->calls[0]['params']['limit'])->toBe(10);
});

it('upcoming uses the injected clock time as the cutoff', function (): void {
    $spy = new SpyTransport();

    $fixedClock = new class implements ClockInterface {
        public function now(): DateTimeImmutable
        {
            return new DateTimeImmutable('2025-01-15 12:00:00');
        }
    };

    makeEventApi($spy, $fixedClock)->upcoming();

    $where = $spy->calls[0]['params']['where'];
    expect($where)->toContain(['start_date', '>', '2025-01-15 12:00:00']);
});

it('upcoming returns a Result of Event DTOs', function (): void {
    $spy = new SpyTransport();
    $spy->queue(singleEventResponse());

    $result = makeEventApi($spy)->upcoming();

    expect($result)->toBeInstanceOf(Result::class)
        ->and($result->first())->toBeInstanceOf(Event::class);
});

// ---------------------------------------------------------------------------
// between
// ---------------------------------------------------------------------------

it('between filters by start_date >= from and start_date <= to', function (): void {
    $spy = new SpyTransport();
    $from = new DateTimeImmutable('2026-01-01 00:00:00');
    $to = new DateTimeImmutable('2026-03-31 23:59:59');

    makeEventApi($spy)->between($from, $to);

    $where = $spy->calls[0]['params']['where'];
    expect($where)->toContain(['start_date', '>=', '2026-01-01 00:00:00'])
        ->and($where)->toContain(['start_date', '<=', '2026-03-31 23:59:59']);
});

it('between orders by start_date ASC', function (): void {
    $spy = new SpyTransport();

    makeEventApi($spy)->between(new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-12-31'));

    expect($spy->calls[0]['params']['orderBy'])->toBe(['start_date' => 'ASC']);
});

it('between returns a Result of Event DTOs', function (): void {
    $spy = new SpyTransport();
    $spy->queue(singleEventResponse());

    $result = makeEventApi($spy)->between(new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-12-31'));

    expect($result)->toBeInstanceOf(Result::class)
        ->and($result->first())->toBeInstanceOf(Event::class);
});

// ---------------------------------------------------------------------------
// participantCount
// ---------------------------------------------------------------------------

it('participantCount sends Participant.get with select=[row_count] and event_id filter', function (): void {
    $spy = new SpyTransport();

    makeEventApi($spy)->participantCount(10);

    expect($spy->calls[0]['entity'])->toBe('Participant')
        ->and($spy->calls[0]['action'])->toBe('get')
        ->and($spy->calls[0]['params']['select'])->toBe(['row_count'])
        ->and($spy->calls[0]['params']['where'])->toContain(['event_id', '=', 10]);
});

it('participantCount without status does not add a status filter', function (): void {
    $spy = new SpyTransport();

    makeEventApi($spy)->participantCount(10);

    $statusClauses = array_filter(callEventWhere($spy, 0), fn($c) => ($c[0] ?? '') === 'status_id:name');
    expect($statusClauses)->toBeEmpty();
});

it('participantCount with status adds status_id:name filter', function (): void {
    $spy = new SpyTransport();

    makeEventApi($spy)->participantCount(10, ParticipantStatus::Registered);

    $where = $spy->calls[0]['params']['where'];
    expect($where)->toContain(['status_id:name', '=', 'Registered']);
});

it('participantCount returns the row_count from the response', function (): void {
    $spy = new SpyTransport();
    $spy->queue(rowCountResponse(42));

    $count = makeEventApi($spy)->participantCount(10);

    expect($count)->toBe(42);
});

it('participantCount returns 0 when response has no row_count', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 0, []));

    $count = makeEventApi($spy)->participantCount(10);

    expect($count)->toBe(0);
});

// ---------------------------------------------------------------------------
// isFull
// ---------------------------------------------------------------------------

it('isFull returns false when event is not found', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 0, []));

    $result = makeEventApi($spy)->isFull(999);

    expect($result)->toBeFalse();
});

it('isFull returns false when maxParticipants is null', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [[
        'id' => 10,
        'title' => 'Unlimited Event',
        'start_date' => '2026-09-15 18:00:00',
        'event_type_id' => 1,
        'is_active' => true,
        'is_public' => true,
    ]]));

    $result = makeEventApi($spy)->isFull(10);

    expect($result)->toBeFalse()
        ->and($spy->calls)->toHaveCount(1);
});

it('isFull returns true when positive-status count equals maxParticipants', function (): void {
    $spy = new SpyTransport();
    $spy->queue(singleEventResponse());
    $spy->queue(rowCountResponse(50));

    $result = makeEventApi($spy)->isFull(10);

    expect($result)->toBeTrue();
});

it('isFull returns true when positive-status count exceeds maxParticipants', function (): void {
    $spy = new SpyTransport();
    $spy->queue(singleEventResponse());
    $spy->queue(rowCountResponse(51));

    $result = makeEventApi($spy)->isFull(10);

    expect($result)->toBeTrue();
});

it('isFull returns false when positive-status count is below maxParticipants', function (): void {
    $spy = new SpyTransport();
    $spy->queue(singleEventResponse());
    $spy->queue(rowCountResponse(49));

    $result = makeEventApi($spy)->isFull(10);

    expect($result)->toBeFalse();
});

it('isFull filters count by Registered and Attended statuses only', function (): void {
    $spy = new SpyTransport();
    $spy->queue(singleEventResponse());
    $spy->queue(rowCountResponse(10));

    makeEventApi($spy)->isFull(10);

    $inClauses = array_values(array_filter(callEventWhere($spy, 1), fn($c) => ($c[1] ?? '') === 'IN'));
    expect($inClauses)->not->toBeEmpty();

    $clause = $inClauses[0];
    $statusValues = is_array($clause[2] ?? null) ? $clause[2] : [];
    expect($statusValues)->toContain('Registered')
        ->and($statusValues)->toContain('Attended');
});

// ---------------------------------------------------------------------------
// getFields / getActions
// ---------------------------------------------------------------------------

it('getFields sends entity=Event and action=getfields', function (): void {
    $spy = new SpyTransport();

    makeEventApi($spy)->getFields();

    expect($spy->calls[0]['entity'])->toBe('Event')
        ->and($spy->calls[0]['action'])->toBe('getfields');
});

it('getActions sends entity=Event and action=getactions', function (): void {
    $spy = new SpyTransport();

    makeEventApi($spy)->getActions();

    expect($spy->calls[0]['entity'])->toBe('Event')
        ->and($spy->calls[0]['action'])->toBe('getactions');
});
