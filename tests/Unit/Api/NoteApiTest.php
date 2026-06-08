<?php

declare(strict_types=1);

use Woduda\CiviCRM\Api\NoteApi;
use Woduda\CiviCRM\Entity\Note;
use Woduda\CiviCRM\Exception\ValidationException;
use Woduda\CiviCRM\Query\GetQuery;
use Woduda\CiviCRM\Query\Operator;
use Woduda\CiviCRM\Result\ApiResponse;
use Woduda\CiviCRM\Result\Result;

// --- addToContact ---

it('addToContact sends entity=Note, action=create with correct values', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 17, 'entity_table' => 'civicrm_contact', 'entity_id' => 42, 'note' => 'Hi', 'modified_date' => '2026-06-08 10:00:00']]));

    (new NoteApi($spy))->addToContact(42, 'Hi');

    expect($spy->calls[0]['entity'])->toBe('Note')
        ->and($spy->calls[0]['action'])->toBe('create')
        ->and($spy->calls[0]['params']['values'])->toBe([
            'entity_table' => 'civicrm_contact',
            'entity_id' => 42,
            'note' => 'Hi',
            'privacy' => 'public',
        ]);
});

it('addToContact includes subject in values when provided', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 17, 'entity_table' => 'civicrm_contact', 'entity_id' => 42, 'note' => 'Hi', 'modified_date' => '2026-06-08 10:00:00']]));

    (new NoteApi($spy))->addToContact(42, 'Hi', 'My Subject');

    expect($spy->calls[0]['params']['values'])->toBe([
        'entity_table' => 'civicrm_contact',
        'entity_id' => 42,
        'note' => 'Hi',
        'privacy' => 'public',
        'subject' => 'My Subject',
    ]);
});

it('addToContact omits subject key when subject is null', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 17, 'entity_table' => 'civicrm_contact', 'entity_id' => 42, 'note' => 'Hi', 'modified_date' => '2026-06-08 10:00:00']]));

    (new NoteApi($spy))->addToContact(42, 'Hi');

    expect($spy->calls[0]['params']['values'])->not->toHaveKey('subject');
});

it('addToContact passes custom privacy value', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 17, 'entity_table' => 'civicrm_contact', 'entity_id' => 42, 'note' => 'Hi', 'modified_date' => '2026-06-08 10:00:00']]));

    (new NoteApi($spy))->addToContact(42, 'Hi', null, 'private');

    expect($spy->calls[0]['params']['values'])->toBe([
        'entity_table' => 'civicrm_contact',
        'entity_id' => 42,
        'note' => 'Hi',
        'privacy' => 'private',
    ]);
});

it('addToContact returns a hydrated Note DTO', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 17, 'entity_table' => 'civicrm_contact', 'entity_id' => 42, 'note' => 'Hi', 'modified_date' => '2026-06-08 10:00:00']]));

    $note = (new NoteApi($spy))->addToContact(42, 'Hi');

    expect($note)->toBeInstanceOf(Note::class)
        ->and($note->id)->toBe(17)
        ->and($note->note)->toBe('Hi');
});

it('addToContact throws ValidationException when API returns empty result', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 0, []));

    expect(fn() => (new NoteApi($spy))->addToContact(42, 'Hi'))
        ->toThrow(ValidationException::class);
});

// --- forContact ---

it('forContact sends entity=Note, action=get with entity_table and entity_id where clauses', function (): void {
    $spy = new SpyTransport();

    (new NoteApi($spy))->forContact(42);

    expect($spy->calls[0]['entity'])->toBe('Note')
        ->and($spy->calls[0]['action'])->toBe('get');

    $where = $spy->calls[0]['params']['where'];
    expect($where)->toContain(['entity_table', '=', 'civicrm_contact'])
        ->and($where)->toContain(['entity_id', '=', 42]);
});

it('forContact orders results by modified_date DESC', function (): void {
    $spy = new SpyTransport();

    (new NoteApi($spy))->forContact(42);

    expect($spy->calls[0]['params']['orderBy'])->toBe(['modified_date' => 'DESC']);
});

it('forContact returns a Result of Note DTOs', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 17, 'entity_table' => 'civicrm_contact', 'entity_id' => 42, 'note' => 'Hi', 'modified_date' => '2026-06-08 10:00:00']]));

    $result = (new NoteApi($spy))->forContact(42);

    expect($result)->toBeInstanceOf(Result::class)
        ->and($result->first())->toBeInstanceOf(Note::class)
        ->and($result->first()?->id)->toBe(17);
});

// --- delete ---

it('delete sends entity=Note, action=delete with where id=noteId', function (): void {
    $spy = new SpyTransport();

    (new NoteApi($spy))->delete(17);

    expect($spy->calls[0]['entity'])->toBe('Note')
        ->and($spy->calls[0]['action'])->toBe('delete')
        ->and($spy->calls[0]['params']['where'])->toBe([['id', '=', 17]]);
});

it('delete makes exactly one transport call', function (): void {
    $spy = new SpyTransport();

    (new NoteApi($spy))->delete(17);

    expect($spy->calls)->toHaveCount(1);
});

// --- get ---

it('get sends entity=Note, action=get with the compiled query params', function (): void {
    $spy = new SpyTransport();
    $query = GetQuery::new()->where('subject', Operator::Equals, 'Intake')->limit(5);

    (new NoteApi($spy))->get($query);

    expect($spy->calls[0]['entity'])->toBe('Note')
        ->and($spy->calls[0]['action'])->toBe('get')
        ->and($spy->calls[0]['params'])->toBe($query->toParams());
});

it('get returns a Result of Note DTOs from the transport response', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 5, 'entity_table' => 'civicrm_contact', 'entity_id' => 10, 'note' => 'Note text', 'modified_date' => '2026-01-01 00:00:00']]));

    $result = (new NoteApi($spy))->get(GetQuery::new());

    expect($result)->toBeInstanceOf(Result::class)
        ->and($result->first())->toBeInstanceOf(Note::class)
        ->and($result->first()?->note)->toBe('Note text');
});

// --- getFields / getActions ---

it('getFields sends entity=Note and action=getfields', function (): void {
    $spy = new SpyTransport();

    (new NoteApi($spy))->getFields();

    expect($spy->calls[0]['entity'])->toBe('Note')
        ->and($spy->calls[0]['action'])->toBe('getfields');
});

it('getActions sends entity=Note and action=getactions', function (): void {
    $spy = new SpyTransport();

    (new NoteApi($spy))->getActions();

    expect($spy->calls[0]['entity'])->toBe('Note')
        ->and($spy->calls[0]['action'])->toBe('getactions');
});
