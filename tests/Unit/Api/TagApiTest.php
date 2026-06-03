<?php

declare(strict_types=1);

use Woduda\CiviCRM\Api\TagApi;
use Woduda\CiviCRM\Result\ApiResponse;

it('ensureExists returns the existing tag id without creating', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 7, 'name' => 'VIP']]));

    $id = (new TagApi($spy))->ensureExists('VIP');

    expect($id)->toBe(7)
        ->and($spy->calls)->toHaveCount(1)
        ->and($spy->calls[0]['entity'])->toBe('Tag')
        ->and($spy->calls[0]['action'])->toBe('get')
        ->and($spy->calls[0]['params']['where'])->toBe([['name', '=', 'VIP']]);
});

it('ensureExists creates the tag when it does not exist and returns new id', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 0, []));            // Tag.get → not found
    $spy->queue(new ApiResponse(4, 1, [['id' => 8]])); // Tag.create → new id

    $id = (new TagApi($spy))->ensureExists('Donor');

    expect($id)->toBe(8)
        ->and($spy->calls)->toHaveCount(2)
        ->and($spy->calls[0]['action'])->toBe('get')
        ->and($spy->calls[1]['action'])->toBe('create')
        ->and($spy->calls[1]['params']['values'])->toBe([
            'name' => 'Donor',
            'used_for' => 'civicrm_contact',
        ]);
});

it('tagContact calls ensureExists then saves EntityTag', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 7]])); // Tag.get → found

    (new TagApi($spy))->tagContact(42, 'VIP');

    expect($spy->calls)->toHaveCount(2)
        ->and($spy->calls[1]['entity'])->toBe('EntityTag')
        ->and($spy->calls[1]['action'])->toBe('save')
        ->and($spy->calls[1]['params']['records'])->toBe([[
            'entity_id' => 42,
            'tag_id' => 7,
            'entity_table' => 'civicrm_contact',
        ]])
        ->and($spy->calls[1]['params']['match'])->toBe(['entity_id', 'tag_id', 'entity_table']);
});

it('tagContact creates missing tag before saving EntityTag', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 0, []));            // Tag.get → not found
    $spy->queue(new ApiResponse(4, 1, [['id' => 9]])); // Tag.create → new id

    (new TagApi($spy))->tagContact(42, 'NewTag');

    expect($spy->calls)->toHaveCount(3)
        ->and($spy->calls[0]['action'])->toBe('get')
        ->and($spy->calls[1]['action'])->toBe('create')
        ->and($spy->calls[2]['entity'])->toBe('EntityTag')
        ->and($spy->calls[2]['params']['records'])->toBe([[
            'entity_id' => 42,
            'tag_id' => 9,
            'entity_table' => 'civicrm_contact',
        ]]);
});

it('getFields sends entity=Tag and action=getfields', function (): void {
    $spy = new SpyTransport();

    (new TagApi($spy))->getFields();

    expect($spy->calls[0]['entity'])->toBe('Tag')
        ->and($spy->calls[0]['action'])->toBe('getfields');
});

it('getActions sends entity=Tag and action=getactions', function (): void {
    $spy = new SpyTransport();

    (new TagApi($spy))->getActions();

    expect($spy->calls[0]['entity'])->toBe('Tag')
        ->and($spy->calls[0]['action'])->toBe('getactions');
});
