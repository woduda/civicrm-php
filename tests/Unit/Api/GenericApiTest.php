<?php

declare(strict_types=1);

use Woduda\CiviCRM\Api\GenericApi;
use Woduda\CiviCRM\Query\GetQuery;
use Woduda\CiviCRM\Query\Operator;
use Woduda\CiviCRM\Result\ApiResponse;

it('get sends entity, action=get, and compiled params to the transport', function (): void {
    $spy = new SpyTransport();
    $query = GetQuery::new()->select('id', 'display_name')->limit(5);

    (new GenericApi($spy, 'Contact'))->get($query);

    expect($spy->calls[0]['entity'])->toBe('Contact')
        ->and($spy->calls[0]['action'])->toBe('get')
        ->and($spy->calls[0]['params'])->toBe($query->toParams());
});

it('get returns the values from the transport response', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 7, 'display_name' => 'Jane']]));

    $result = (new GenericApi($spy, 'Contact'))->get(GetQuery::new());

    expect($result)->toBe([['id' => 7, 'display_name' => 'Jane']]);
});

it('create sends action=create with values wrapped in a values key', function (): void {
    $spy = new SpyTransport();

    (new GenericApi($spy, 'Contact'))->create(['first_name' => 'Jane', 'contact_type' => 'Individual']);

    expect($spy->calls[0]['entity'])->toBe('Contact')
        ->and($spy->calls[0]['action'])->toBe('create')
        ->and($spy->calls[0]['params']['values'])->toBe(['first_name' => 'Jane', 'contact_type' => 'Individual']);
});

it('update sends action=update with values and a raw where array', function (): void {
    $spy = new SpyTransport();
    $where = [['id', '=', 42]];

    (new GenericApi($spy, 'Contact'))->update(['first_name' => 'John'], $where);

    expect($spy->calls[0]['action'])->toBe('update')
        ->and($spy->calls[0]['params']['values'])->toBe(['first_name' => 'John'])
        ->and($spy->calls[0]['params']['where'])->toBe($where);
});

it('update extracts where clauses from a GetQuery', function (): void {
    $spy = new SpyTransport();
    $query = GetQuery::new()->where('id', Operator::Equals, 5);

    (new GenericApi($spy, 'Contact'))->update(['first_name' => 'Jane'], $query);

    expect($spy->calls[0]['params']['where'])->toBe($query->toParams()['where']);
});

it('update resolves an empty GetQuery where to []', function (): void {
    $spy = new SpyTransport();

    (new GenericApi($spy, 'Contact'))->update(['first_name' => 'Jane'], GetQuery::new());

    expect($spy->calls[0]['params']['where'])->toBe([]);
});

it('save sends action=save with records', function (): void {
    $spy = new SpyTransport();
    $records = [['first_name' => 'A'], ['first_name' => 'B']];

    (new GenericApi($spy, 'Contact'))->save($records);

    expect($spy->calls[0]['action'])->toBe('save')
        ->and($spy->calls[0]['params']['records'])->toBe($records);
});

it('delete sends action=delete with a raw where array', function (): void {
    $spy = new SpyTransport();
    $where = [['id', '=', 42]];

    (new GenericApi($spy, 'Contact'))->delete($where);

    expect($spy->calls[0]['action'])->toBe('delete')
        ->and($spy->calls[0]['params']['where'])->toBe($where);
});

it('delete extracts where clauses from a GetQuery', function (): void {
    $spy = new SpyTransport();
    $query = GetQuery::new()->where('id', Operator::Equals, 3);

    (new GenericApi($spy, 'Contact'))->delete($query);

    expect($spy->calls[0]['params']['where'])->toBe($query->toParams()['where']);
});

it('delete resolves an empty GetQuery where to []', function (): void {
    $spy = new SpyTransport();

    (new GenericApi($spy, 'Contact'))->delete(GetQuery::new());

    expect($spy->calls[0]['params']['where'])->toBe([]);
});

it('getFields sends entity and action=getfields with empty params', function (): void {
    $spy = new SpyTransport();

    (new GenericApi($spy, 'Contact'))->getFields();

    expect($spy->calls[0]['entity'])->toBe('Contact')
        ->and($spy->calls[0]['action'])->toBe('getfields')
        ->and($spy->calls[0]['params'])->toBe([]);
});

it('getActions sends entity and action=getactions with empty params', function (): void {
    $spy = new SpyTransport();

    (new GenericApi($spy, 'Contact'))->getActions();

    expect($spy->calls[0]['entity'])->toBe('Contact')
        ->and($spy->calls[0]['action'])->toBe('getactions')
        ->and($spy->calls[0]['params'])->toBe([]);
});

it('each call dispatches to the correct entity when entity differs', function (): void {
    $spy = new SpyTransport();

    (new GenericApi($spy, 'Tag'))->get(GetQuery::new());
    (new GenericApi($spy, 'Activity'))->get(GetQuery::new());

    expect($spy->calls[0]['entity'])->toBe('Tag')
        ->and($spy->calls[1]['entity'])->toBe('Activity');
});
