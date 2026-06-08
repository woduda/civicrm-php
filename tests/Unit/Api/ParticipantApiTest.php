<?php

declare(strict_types=1);

use Woduda\CiviCRM\Api\ParticipantApi;
use Woduda\CiviCRM\Entity\Participant;
use Woduda\CiviCRM\Entity\ParticipantStatus;
use Woduda\CiviCRM\Exception\ValidationException;
use Woduda\CiviCRM\Query\GetQuery;
use Woduda\CiviCRM\Query\Operator;
use Woduda\CiviCRM\Result\ApiResponse;
use Woduda\CiviCRM\Result\Result;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeParticipantApi(SpyTransport $spy): ParticipantApi
{
    return new ParticipantApi($spy);
}

function singleParticipantResponse(): ApiResponse
{
    return new ApiResponse(4, 1, [fixtureFirstRow('participant-single.json')]);
}

/**
 * @return array<mixed, mixed>
 */
function participantCallValues(SpyTransport $spy, int $callIndex): array
{
    $val = $spy->calls[$callIndex]['params']['values'] ?? [];

    return is_array($val) ? $val : [];
}

/**
 * Narrows params['where'] to list<list<mixed>>.
 *
 * @return list<list<mixed>>
 */
function callParticipantWhere(SpyTransport $spy, int $callIndex): array
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

it('get sends entity=Participant, action=get with compiled query params', function (): void {
    $spy = new SpyTransport();
    $query = GetQuery::new()->where('event_id', Operator::Equals, 10)->limit(5);

    makeParticipantApi($spy)->get($query);

    expect($spy->calls[0]['entity'])->toBe('Participant')
        ->and($spy->calls[0]['action'])->toBe('get')
        ->and($spy->calls[0]['params'])->toBe($query->toParams());
});

// ---------------------------------------------------------------------------
// register
// ---------------------------------------------------------------------------

it('register sends Participant.create with required fields', function (): void {
    $spy = new SpyTransport();
    $spy->queue(singleParticipantResponse());

    makeParticipantApi($spy)->register(42, 10);

    expect($spy->calls[0]['entity'])->toBe('Participant')
        ->and($spy->calls[0]['action'])->toBe('create')
        ->and(participantCallValues($spy, 0)['contact_id'])->toBe(42)
        ->and(participantCallValues($spy, 0)['event_id'])->toBe(10)
        ->and(participantCallValues($spy, 0)['status_id:name'])->toBe('Registered');
});

it('register uses Registered as the default status', function (): void {
    $spy = new SpyTransport();
    $spy->queue(singleParticipantResponse());

    makeParticipantApi($spy)->register(42, 10);

    expect(participantCallValues($spy, 0)['status_id:name'])->toBe('Registered');
});

it('register accepts an explicit status override', function (): void {
    $spy = new SpyTransport();
    $spy->queue(singleParticipantResponse());

    makeParticipantApi($spy)->register(42, 10, ParticipantStatus::OnWaitlist);

    expect(participantCallValues($spy, 0)['status_id:name'])->toBe('On waitlist');
});

it('register includes roleId when provided', function (): void {
    $spy = new SpyTransport();
    $spy->queue(singleParticipantResponse());

    makeParticipantApi($spy)->register(42, 10, roleId: 1);

    expect(participantCallValues($spy, 0)['role_id'])->toBe(1);
});

it('register omits roleId when null', function (): void {
    $spy = new SpyTransport();
    $spy->queue(singleParticipantResponse());

    makeParticipantApi($spy)->register(42, 10);

    expect($spy->calls[0]['params']['values'])->not->toHaveKey('role_id');
});

it('register includes source when provided', function (): void {
    $spy = new SpyTransport();
    $spy->queue(singleParticipantResponse());

    makeParticipantApi($spy)->register(42, 10, source: 'Website');

    expect(participantCallValues($spy, 0)['source'])->toBe('Website');
});

it('register merges customFields into values', function (): void {
    $spy = new SpyTransport();
    $spy->queue(singleParticipantResponse());

    makeParticipantApi($spy)->register(42, 10, customFields: ['CustomGroup.meal_preference' => 'Vegan']);

    expect(participantCallValues($spy, 0)['CustomGroup.meal_preference'])->toBe('Vegan');
});

it('register returns a hydrated Participant DTO', function (): void {
    $spy = new SpyTransport();
    $spy->queue(singleParticipantResponse());

    $participant = makeParticipantApi($spy)->register(42, 10);

    expect($participant)->toBeInstanceOf(Participant::class)
        ->and($participant->id)->toBe(1);
});

it('register throws ValidationException when API returns empty result', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 0, []));

    expect(fn() => makeParticipantApi($spy)->register(42, 10))
        ->toThrow(ValidationException::class);
});

// ---------------------------------------------------------------------------
// markAttended
// ---------------------------------------------------------------------------

it('markAttended sends Participant.update with status=Attended', function (): void {
    $spy = new SpyTransport();

    makeParticipantApi($spy)->markAttended(1);

    expect($spy->calls[0]['entity'])->toBe('Participant')
        ->and($spy->calls[0]['action'])->toBe('update')
        ->and($spy->calls[0]['params']['where'])->toBe([['id', '=', 1]])
        ->and(participantCallValues($spy, 0)['status_id:name'])->toBe('Attended');
});

it('markAttended makes exactly one transport call', function (): void {
    $spy = new SpyTransport();

    makeParticipantApi($spy)->markAttended(1);

    expect($spy->calls)->toHaveCount(1);
});

// ---------------------------------------------------------------------------
// cancel
// ---------------------------------------------------------------------------

it('cancel sends Participant.update with status=Cancelled', function (): void {
    $spy = new SpyTransport();

    makeParticipantApi($spy)->cancel(1);

    expect($spy->calls[0]['entity'])->toBe('Participant')
        ->and($spy->calls[0]['action'])->toBe('update')
        ->and($spy->calls[0]['params']['where'])->toBe([['id', '=', 1]])
        ->and(participantCallValues($spy, 0)['status_id:name'])->toBe('Cancelled');
});

it('cancel without reason makes exactly one transport call', function (): void {
    $spy = new SpyTransport();

    makeParticipantApi($spy)->cancel(1);

    expect($spy->calls)->toHaveCount(1);
});

it('cancel with reason makes three transport calls (update, get contact, create activity)', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, []));
    $spy->queue(new ApiResponse(4, 1, [['id' => 1, 'contact_id' => 42]]));
    $spy->queue(new ApiResponse(4, 1, []));

    makeParticipantApi($spy)->cancel(1, 'Travel conflict');

    expect($spy->calls)->toHaveCount(3)
        ->and($spy->calls[1]['entity'])->toBe('Participant')
        ->and($spy->calls[1]['action'])->toBe('get')
        ->and($spy->calls[2]['entity'])->toBe('Activity')
        ->and($spy->calls[2]['action'])->toBe('create');
});

it('cancel with reason sets activity details to the reason', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, []));
    $spy->queue(new ApiResponse(4, 1, [['id' => 1, 'contact_id' => 42]]));
    $spy->queue(new ApiResponse(4, 1, []));

    makeParticipantApi($spy)->cancel(1, 'Travel conflict');

    $vals = participantCallValues($spy, 2);
    expect($vals['details'])->toBe('Travel conflict')
        ->and($vals['source_contact_id'])->toBe(42);
});

it('cancel with reason uses Follow Up activity type', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, []));
    $spy->queue(new ApiResponse(4, 1, [['id' => 1, 'contact_id' => 42]]));
    $spy->queue(new ApiResponse(4, 1, []));

    makeParticipantApi($spy)->cancel(1, 'No longer available');

    expect(participantCallValues($spy, 2)['activity_type_id:name'])->toBe('Follow Up');
});

// ---------------------------------------------------------------------------
// checkIn
// ---------------------------------------------------------------------------

it('checkIn sends Participant.update with status=Attended', function (): void {
    $spy = new SpyTransport();

    makeParticipantApi($spy)->checkIn(1);

    expect($spy->calls[0]['entity'])->toBe('Participant')
        ->and($spy->calls[0]['action'])->toBe('update')
        ->and(participantCallValues($spy, 0)['status_id:name'])->toBe('Attended');
});

it('checkIn without $at makes exactly one transport call', function (): void {
    $spy = new SpyTransport();

    makeParticipantApi($spy)->checkIn(1);

    expect($spy->calls)->toHaveCount(1);
});

it('checkIn with $at makes three transport calls (update, get contact, create activity)', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, []));
    $spy->queue(new ApiResponse(4, 1, [['id' => 1, 'contact_id' => 42]]));
    $spy->queue(new ApiResponse(4, 1, []));

    makeParticipantApi($spy)->checkIn(1, new DateTimeImmutable('2026-06-15 09:30:00'));

    expect($spy->calls)->toHaveCount(3)
        ->and($spy->calls[2]['entity'])->toBe('Activity')
        ->and($spy->calls[2]['action'])->toBe('create');
});

it('checkIn with $at creates Check-in activity with correct timestamp', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, []));
    $spy->queue(new ApiResponse(4, 1, [['id' => 1, 'contact_id' => 42]]));
    $spy->queue(new ApiResponse(4, 1, []));

    $at = new DateTimeImmutable('2026-06-15 09:30:00');
    makeParticipantApi($spy)->checkIn(1, $at);

    $vals = participantCallValues($spy, 2);
    expect($vals['activity_type_id:name'])->toBe('Check-in')
        ->and($vals['activity_date_time'])->toBe('2026-06-15 09:30:00')
        ->and($vals['source_contact_id'])->toBe(42);
});

// ---------------------------------------------------------------------------
// forEvent
// ---------------------------------------------------------------------------

it('forEvent sends where event_id=X', function (): void {
    $spy = new SpyTransport();

    makeParticipantApi($spy)->forEvent(10);

    expect($spy->calls[0]['entity'])->toBe('Participant')
        ->and($spy->calls[0]['action'])->toBe('get')
        ->and($spy->calls[0]['params']['where'])->toContain(['event_id', '=', 10]);
});

it('forEvent without status does not add status filter', function (): void {
    $spy = new SpyTransport();

    makeParticipantApi($spy)->forEvent(10);

    $statusClauses = array_filter(callParticipantWhere($spy, 0), fn($c) => ($c[0] ?? '') === 'status_id:name');
    expect($statusClauses)->toBeEmpty();
});

it('forEvent with status adds status_id:name filter', function (): void {
    $spy = new SpyTransport();

    makeParticipantApi($spy)->forEvent(10, ParticipantStatus::Attended);

    $where = $spy->calls[0]['params']['where'];
    expect($where)->toContain(['status_id:name', '=', 'Attended']);
});

it('forEvent returns a Result of Participant DTOs', function (): void {
    $spy = new SpyTransport();
    $spy->queue(singleParticipantResponse());

    $result = makeParticipantApi($spy)->forEvent(10);

    expect($result)->toBeInstanceOf(Result::class)
        ->and($result->first())->toBeInstanceOf(Participant::class);
});

// ---------------------------------------------------------------------------
// forContact
// ---------------------------------------------------------------------------

it('forContact sends where contact_id=X and orderBy register_date DESC', function (): void {
    $spy = new SpyTransport();

    makeParticipantApi($spy)->forContact(42);

    expect($spy->calls[0]['entity'])->toBe('Participant')
        ->and($spy->calls[0]['action'])->toBe('get')
        ->and($spy->calls[0]['params']['where'])->toContain(['contact_id', '=', 42])
        ->and($spy->calls[0]['params']['orderBy'])->toBe(['register_date' => 'DESC']);
});

it('forContact returns a Result of Participant DTOs', function (): void {
    $spy = new SpyTransport();
    $spy->queue(singleParticipantResponse());

    $result = makeParticipantApi($spy)->forContact(42);

    expect($result)->toBeInstanceOf(Result::class)
        ->and($result->first())->toBeInstanceOf(Participant::class);
});

// ---------------------------------------------------------------------------
// countByStatus
// ---------------------------------------------------------------------------

it('countByStatus sends Participant.get with groupBy status_id', function (): void {
    $spy = new SpyTransport();

    makeParticipantApi($spy)->countByStatus(10);

    expect($spy->calls[0]['entity'])->toBe('Participant')
        ->and($spy->calls[0]['action'])->toBe('get')
        ->and($spy->calls[0]['params']['groupBy'])->toBe(['status_id'])
        ->and($spy->calls[0]['params']['where'])->toContain(['event_id', '=', 10]);
});

it('countByStatus select includes status_id:name and COUNT(id)', function (): void {
    $spy = new SpyTransport();

    makeParticipantApi($spy)->countByStatus(10);

    $select = $spy->calls[0]['params']['select'];
    expect($select)->toContain('status_id:name')
        ->and($select)->toContain('COUNT(id)');
});

it('countByStatus returns correct name-to-count map from fixture', function (): void {
    $spy = new SpyTransport();
    $payload = fixtureApiPayload('participant-count-by-status.json');
    $spy->queue(new ApiResponse(4, $payload['count'], $payload['values']));

    $result = makeParticipantApi($spy)->countByStatus(10);

    expect($result)->toBe([
        'Registered' => 45,
        'Attended' => 3,
        'Cancelled' => 2,
    ]);
});

it('countByStatus returns empty array when no participants', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 0, []));

    $result = makeParticipantApi($spy)->countByStatus(10);

    expect($result)->toBe([]);
});

// ---------------------------------------------------------------------------
// getFields / getActions
// ---------------------------------------------------------------------------

it('getFields sends entity=Participant and action=getfields', function (): void {
    $spy = new SpyTransport();

    makeParticipantApi($spy)->getFields();

    expect($spy->calls[0]['entity'])->toBe('Participant')
        ->and($spy->calls[0]['action'])->toBe('getfields');
});

it('getActions sends entity=Participant and action=getactions', function (): void {
    $spy = new SpyTransport();

    makeParticipantApi($spy)->getActions();

    expect($spy->calls[0]['entity'])->toBe('Participant')
        ->and($spy->calls[0]['action'])->toBe('getactions');
});
