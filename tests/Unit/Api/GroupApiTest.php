<?php

declare(strict_types=1);

use Woduda\CiviCRM\Api\GroupApi;
use Woduda\CiviCRM\Result\ApiResponse;

it('ensureExists returns the existing group id without creating', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 3, 'title' => 'Newsletter']]));

    $id = (new GroupApi($spy))->ensureExists('Newsletter');

    expect($id)->toBe(3)
        ->and($spy->calls)->toHaveCount(1)
        ->and($spy->calls[0]['entity'])->toBe('Group')
        ->and($spy->calls[0]['action'])->toBe('get')
        ->and($spy->calls[0]['params']['where'])->toBe([['title', '=', 'Newsletter']]);
});

it('ensureExists creates the group when it does not exist and returns new id', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 0, []));            // Group.get → not found
    $spy->queue(new ApiResponse(4, 1, [['id' => 4]])); // Group.create → new id

    $id = (new GroupApi($spy))->ensureExists('Volunteers');

    expect($id)->toBe(4)
        ->and($spy->calls)->toHaveCount(2)
        ->and($spy->calls[0]['action'])->toBe('get')
        ->and($spy->calls[1]['action'])->toBe('create')
        ->and($spy->calls[1]['params']['values'])->toBe(['title' => 'Volunteers']);
});

it('ensureExists sends select=[id] and limit=1 in the Group.get query', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 3]]));

    (new GroupApi($spy))->ensureExists('Newsletter');

    expect($spy->calls[0]['params']['select'])->toBe(['id'])
        ->and($spy->calls[0]['params']['limit'])->toBe(1);
});

it('ensureExists returns 0 when found row has no integer id', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['title' => 'Newsletter']])); // no id key

    $id = (new GroupApi($spy))->ensureExists('Newsletter');

    expect($id)->toBe(0);
});

it('ensureExists returns 0 when Group.create returns no records', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 0, []));  // Group.get → not found
    $spy->queue(new ApiResponse(4, 0, []));  // Group.create → empty

    $id = (new GroupApi($spy))->ensureExists('Ghost');

    expect($id)->toBe(0);
});

it('ensureExists returns 0 when Group.create returns a row without an integer id', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 0, []));                        // Group.get → not found
    $spy->queue(new ApiResponse(4, 1, [['title' => 'Ghost']]));    // Group.create → no id

    $id = (new GroupApi($spy))->ensureExists('Ghost');

    expect($id)->toBe(0);
});

it('addContact saves a GroupContact record with status=Added', function (): void {
    $spy = new SpyTransport();

    (new GroupApi($spy))->addContact(42, 3);

    expect($spy->calls)->toHaveCount(1)
        ->and($spy->calls[0]['entity'])->toBe('GroupContact')
        ->and($spy->calls[0]['action'])->toBe('save')
        ->and($spy->calls[0]['params']['records'])->toBe([[
            'contact_id' => 42,
            'group_id' => 3,
            'status' => 'Added',
        ]])
        ->and($spy->calls[0]['params']['match'])->toBe(['contact_id', 'group_id']);
});

it('removeContact sends a GroupContact update with status=Removed', function (): void {
    $spy = new SpyTransport();

    (new GroupApi($spy))->removeContact(42, 3);

    expect($spy->calls)->toHaveCount(1)
        ->and($spy->calls[0]['entity'])->toBe('GroupContact')
        ->and($spy->calls[0]['action'])->toBe('update')
        ->and($spy->calls[0]['params']['values'])->toBe(['status' => 'Removed'])
        ->and($spy->calls[0]['params']['where'])->toBe([
            ['contact_id', '=', 42],
            ['group_id', '=', 3],
        ]);
});

it('getFields sends entity=Group and action=getfields', function (): void {
    $spy = new SpyTransport();

    (new GroupApi($spy))->getFields();

    expect($spy->calls[0]['entity'])->toBe('Group')
        ->and($spy->calls[0]['action'])->toBe('getfields');
});

it('getActions sends entity=Group and action=getactions', function (): void {
    $spy = new SpyTransport();

    (new GroupApi($spy))->getActions();

    expect($spy->calls[0]['entity'])->toBe('Group')
        ->and($spy->calls[0]['action'])->toBe('getactions');
});
