<?php

declare(strict_types=1);

use Woduda\CiviCRM\Api\GenericApi;
use Woduda\CiviCRM\CiviCrmClient;
use Woduda\CiviCRM\Config;
use Woduda\CiviCRM\Result\ApiResponse;

it('entity() returns a GenericApi instance', function (): void {
    [$client] = civicrmNewClient();

    expect($client->entity('Tag'))->toBeInstanceOf(GenericApi::class);
});

it('entity() wires the given entity name to the GenericApi', function (): void {
    [$client, $spy] = civicrmNewClient();
    $client->entity('Tag')->getFields();

    expect($spy->calls[0]['entity'])->toBe('Tag');
});

it('contacts() targets the Contact entity', function (): void {
    [$client, $spy] = civicrmNewClient();
    $client->contacts()->getFields();

    expect($spy->calls[0]['entity'])->toBe('Contact');
});

it('activities() targets the Activity entity', function (): void {
    [$client, $spy] = civicrmNewClient();
    $client->activities()->getFields();

    expect($spy->calls[0]['entity'])->toBe('Activity');
});

it('tags() targets the Tag entity', function (): void {
    [$client, $spy] = civicrmNewClient();
    $client->tags()->getFields();

    expect($spy->calls[0]['entity'])->toBe('Tag');
});

it('groups() targets the Group entity', function (): void {
    [$client, $spy] = civicrmNewClient();
    $client->groups()->getFields();

    expect($spy->calls[0]['entity'])->toBe('Group');
});

it('emails() targets the Email entity', function (): void {
    [$client, $spy] = civicrmNewClient();
    $client->emails()->getFields();

    expect($spy->calls[0]['entity'])->toBe('Email');
});

it('phones() targets the Phone entity', function (): void {
    [$client, $spy] = civicrmNewClient();
    $client->phones()->getFields();

    expect($spy->calls[0]['entity'])->toBe('Phone');
});

it('addresses() targets the Address entity', function (): void {
    [$client, $spy] = civicrmNewClient();
    $client->addresses()->getFields();

    expect($spy->calls[0]['entity'])->toBe('Address');
});

it('relationships() targets the Relationship entity', function (): void {
    [$client, $spy] = civicrmNewClient();
    $client->relationships()->getFields();

    expect($spy->calls[0]['entity'])->toBe('Relationship');
});

it('relationshipTypes() targets the RelationshipType entity', function (): void {
    [$client, $spy] = civicrmNewClient();
    $client->relationshipTypes()->getFields();

    expect($spy->calls[0]['entity'])->toBe('RelationshipType');
});

it('notes() targets the Note entity', function (): void {
    [$client, $spy] = civicrmNewClient();
    $client->notes()->getFields();

    expect($spy->calls[0]['entity'])->toBe('Note');
});

it('raw() calls the transport with the given entity, action, and params', function (): void {
    [$client, $spy] = civicrmNewClient();
    $client->raw('Contact', 'get', ['limit' => 5]);

    expect($spy->calls[0]['entity'])->toBe('Contact')
        ->and($spy->calls[0]['action'])->toBe('get')
        ->and($spy->calls[0]['params'])->toBe(['limit' => 5]);
});

it('raw() returns the transport response values', function (): void {
    [$client, $spy] = civicrmNewClient();
    $spy->queue(new ApiResponse(4, 2, [['id' => 1], ['id' => 2]]));

    expect($client->raw('Contact', 'get'))->toBe([['id' => 1], ['id' => 2]]);
});

it('create() builds a CiviCrmClient with an auto-discovered HTTP transport', function (): void {
    $client = CiviCrmClient::create(new Config('https://crm.example.org/civicrm/ajax/api4/', 'k'));

    expect($client)->toBeInstanceOf(CiviCrmClient::class);
});

it('create() produces a working client whose entity() returns GenericApi', function (): void {
    $client = CiviCrmClient::create(new Config('https://crm.example.org/civicrm/ajax/api4/', 'k'));

    expect($client->entity('Contact'))->toBeInstanceOf(GenericApi::class);
});
