<?php

declare(strict_types=1);

use Nyholm\Psr7\Response;
use Woduda\CiviCRM\Api\ActivitiesApi;
use Woduda\CiviCRM\Api\AddressesApi;
use Woduda\CiviCRM\Api\ContactsApi;
use Woduda\CiviCRM\Api\ContributionsApi;
use Woduda\CiviCRM\Api\EmailsApi;
use Woduda\CiviCRM\Api\EntitiesApi;
use Woduda\CiviCRM\Api\EventsApi;
use Woduda\CiviCRM\Api\ParticipantsApi;
use Woduda\CiviCRM\Api\PhonesApi;
use Woduda\CiviCRM\Result\ApiResponse;

it('routes every standard action to the matching APIv4 endpoint', function (): void {
    [$client, $mock] = civicrmClient();

    $actions = [
        [fn(EntitiesApi $api): ApiResponse => $api->get(['x' => 1]), 'get'],
        [fn(EntitiesApi $api): ApiResponse => $api->create(['x' => 1]), 'create'],
        [fn(EntitiesApi $api): ApiResponse => $api->update(['x' => 1]), 'update'],
        [fn(EntitiesApi $api): ApiResponse => $api->save(['x' => 1]), 'save'],
        [fn(EntitiesApi $api): ApiResponse => $api->delete(['x' => 1]), 'delete'],
        [fn(EntitiesApi $api): ApiResponse => $api->replace(['x' => 1]), 'replace'],
        [fn(EntitiesApi $api): ApiResponse => $api->getActions(), 'getactions'],
        [fn(EntitiesApi $api): ApiResponse => $api->getFields(), 'getfields'],
    ];

    foreach ($actions as [$call, $action]) {
        $mock->addResponse(new Response(200, [], '{"count":0,"values":[]}'));
        $call($client->contacts());
        expect(lastRequestUri($mock))->toEndWith('Contact/' . $action);
    }
});

it('maps each accessor to the correct entity class and entity name', function (): void {
    [$client, $mock] = civicrmClient();

    $cases = [
        [$client->activities(), ActivitiesApi::class, 'Activity'],
        [$client->addresses(), AddressesApi::class, 'Address'],
        [$client->contacts(), ContactsApi::class, 'Contact'],
        [$client->contributions(), ContributionsApi::class, 'Contribution'],
        [$client->emails(), EmailsApi::class, 'Email'],
        [$client->events(), EventsApi::class, 'Event'],
        [$client->participants(), ParticipantsApi::class, 'Participant'],
        [$client->phones(), PhonesApi::class, 'Phone'],
    ];

    foreach ($cases as [$api, $class, $entity]) {
        expect($api)->toBeInstanceOf($class);

        $mock->addResponse(new Response(200, [], '{"count":0,"values":[]}'));
        $api->get();
        expect(lastRequestUri($mock))->toEndWith($entity . '/get');
    }
});

it('caches accessor instances', function (): void {
    [$client] = civicrmClient();

    $accessors = [
        $client->activities(...),
        $client->addresses(...),
        $client->contacts(...),
        $client->contributions(...),
        $client->emails(...),
        $client->events(...),
        $client->participants(...),
        $client->phones(...),
    ];

    foreach ($accessors as $accessor) {
        expect($accessor())->toBe($accessor());
    }
});
