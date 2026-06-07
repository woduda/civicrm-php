<?php

declare(strict_types=1);

use Woduda\CiviCRM\Api\ActivityApi;
use Woduda\CiviCRM\Entity\Activity;
use Woduda\CiviCRM\Query\GetQuery;
use Woduda\CiviCRM\Query\Operator;
use Woduda\CiviCRM\Result\ApiResponse;
use Woduda\CiviCRM\Result\Result;

it('get sends entity=Activity, action=get, and compiled params', function (): void {
    $spy = new SpyTransport();
    $query = GetQuery::new()->select('id', 'subject')->limit(5);

    (new ActivityApi($spy))->get($query);

    expect($spy->calls[0]['entity'])->toBe('Activity')
        ->and($spy->calls[0]['action'])->toBe('get')
        ->and($spy->calls[0]['params'])->toBe($query->toParams());
});

it('get returns a Result of Activity DTOs from the transport response', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 1, 'subject' => 'Call']]));

    $result = (new ActivityApi($spy))->get(GetQuery::new());

    expect($result)->toBeInstanceOf(Result::class)
        ->and($result->first())->toBeInstanceOf(Activity::class)
        ->and($result->first()?->id)->toBe(1)
        ->and($result->first()?->subject)->toBe('Call');
});

it('create sends action=create with values', function (): void {
    $spy = new SpyTransport();

    (new ActivityApi($spy))->create(['subject' => 'Meeting', 'activity_type_id.name' => 'Meeting']);

    expect($spy->calls[0]['entity'])->toBe('Activity')
        ->and($spy->calls[0]['action'])->toBe('create')
        ->and($spy->calls[0]['params']['values'])->toBe([
            'subject' => 'Meeting',
            'activity_type_id.name' => 'Meeting',
        ]);
});

it('logForContact sets source_contact_id, activity_type_id.name, and status_id.name=Completed', function (): void {
    $spy = new SpyTransport();

    (new ActivityApi($spy))->logForContact(42, 'Phone Call');

    expect($spy->calls[0]['params']['values'])->toBe([
        'activity_type_id.name' => 'Phone Call',
        'source_contact_id' => 42,
        'status_id.name' => 'Completed',
    ]);
});

it('logForContact allows $extra to override default values', function (): void {
    $spy = new SpyTransport();

    (new ActivityApi($spy))->logForContact(42, 'Meeting', ['status_id.name' => 'Scheduled', 'subject' => 'Kickoff']);

    expect($spy->calls[0]['params']['values'])->toBe([
        'activity_type_id.name' => 'Meeting',
        'source_contact_id' => 42,
        'status_id.name' => 'Scheduled',
        'subject' => 'Kickoff',
    ]);
});

it('forContact returns a GetQuery with where source_contact_id = contactId', function (): void {
    $spy = new SpyTransport();
    $query = (new ActivityApi($spy))->forContact(42);

    expect($query)->toBeInstanceOf(GetQuery::class);

    $params = $query->toParams();
    expect($params['where'])->toBe(
        GetQuery::new()->where('source_contact_id', Operator::Equals, 42)->toParams()['where'],
    );
});

it('forContact returns a query that can be chained further', function (): void {
    $spy = new SpyTransport();
    $query = (new ActivityApi($spy))->forContact(42)->select('id', 'subject')->limit(10);

    $params = $query->toParams();
    expect($params['select'])->toBe(['id', 'subject'])
        ->and($params['limit'])->toBe(10);
});

it('getFields sends entity=Activity and action=getfields', function (): void {
    $spy = new SpyTransport();

    (new ActivityApi($spy))->getFields();

    expect($spy->calls[0]['entity'])->toBe('Activity')
        ->and($spy->calls[0]['action'])->toBe('getfields');
});

it('getActions sends entity=Activity and action=getactions', function (): void {
    $spy = new SpyTransport();

    (new ActivityApi($spy))->getActions();

    expect($spy->calls[0]['entity'])->toBe('Activity')
        ->and($spy->calls[0]['action'])->toBe('getactions');
});
