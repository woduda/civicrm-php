<?php

declare(strict_types=1);

use Woduda\CiviCRM\Api\EmailApi;
use Woduda\CiviCRM\Entity\Email;
use Woduda\CiviCRM\Exception\ValidationException;
use Woduda\CiviCRM\Query\GetQuery;
use Woduda\CiviCRM\Query\Operator;
use Woduda\CiviCRM\Result\ApiResponse;
use Woduda\CiviCRM\Result\Result;

function makeEmailApi(SpyTransport $spy): EmailApi
{
    return new EmailApi($spy);
}

it('get sends entity=Email, action=get, and compiled params', function (): void {
    $spy = new SpyTransport();
    $query = GetQuery::new()->select('id', 'email')->limit(5);

    makeEmailApi($spy)->get($query);

    expect($spy->calls[0]['entity'])->toBe('Email')
        ->and($spy->calls[0]['action'])->toBe('get')
        ->and($spy->calls[0]['params'])->toBe($query->toParams());
});

it('get returns a Result of Email DTOs from the transport response', function (): void {
    $spy = new SpyTransport();
    $payload = fixtureApiPayload('email_single.json');
    $spy->queue(new ApiResponse(4, $payload['count'], $payload['values']));

    $result = makeEmailApi($spy)->get(GetQuery::new());

    expect($result)->toBeInstanceOf(Result::class)
        ->and($result->first())->toBeInstanceOf(Email::class)
        ->and($result->first()?->email)->toBe('new@example.org');
});

it('forContact sends get with contact_id where and is_primary order', function (): void {
    $spy = new SpyTransport();
    $payload = fixtureApiPayload('emails_for_contact.json');
    $spy->queue(new ApiResponse(4, $payload['count'], $payload['values']));

    makeEmailApi($spy)->forContact(42);

    expect($spy->calls[0])->toMatchArray([
        'entity' => 'Email',
        'action' => 'get',
    ])
        ->and($spy->calls[0]['params']['where'])->toBe(
            GetQuery::new()->where('contact_id', Operator::Equals, 42)->toParams()['where'],
        )
        ->and($spy->calls[0]['params']['orderBy'])->toBe(['is_primary' => 'DESC']);
});

it('primary returns the email marked is_primary from forContact results', function (): void {
    $spy = new SpyTransport();
    $payload = fixtureApiPayload('emails_for_contact.json');
    $spy->queue(new ApiResponse(4, $payload['count'], $payload['values']));

    $primary = makeEmailApi($spy)->primary(42);

    expect($primary)->toBeInstanceOf(Email::class)
        ->and($primary?->id)->toBe(101)
        ->and($primary?->isPrimary)->toBeTrue();
});

it('primary returns null when no email is marked primary', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [[
        'id' => 102,
        'contact_id' => 42,
        'email' => 'secondary@example.org',
        'is_primary' => false,
    ]]));

    expect(makeEmailApi($spy)->primary(42))->toBeNull();
});

it('setPrimary sends a single update with is_primary=true', function (): void {
    $spy = new SpyTransport();

    makeEmailApi($spy)->setPrimary(101);

    expect($spy->calls)->toHaveCount(1)
        ->and($spy->calls[0]['entity'])->toBe('Email')
        ->and($spy->calls[0]['action'])->toBe('update')
        ->and($spy->calls[0]['params']['values'])->toBe(['is_primary' => true])
        ->and($spy->calls[0]['params']['where'])->toBe([['id', '=', 101]]);
});

it('add sends action=create with the expected values map', function (): void {
    $spy = new SpyTransport();
    $payload = fixtureApiPayload('email_single.json');
    $spy->queue(new ApiResponse(4, $payload['count'], $payload['values']));

    makeEmailApi($spy)->add(42, 'new@example.org', 'Home', isPrimary: true, onHold: false);

    expect($spy->calls[0]['action'])->toBe('create')
        ->and($spy->calls[0]['params']['values'])->toBe([
            'contact_id' => 42,
            'email' => 'new@example.org',
            'location_type_id.name' => 'Home',
            'is_primary' => true,
            'on_hold' => false,
        ]);
});

it('add omits on_hold when not provided', function (): void {
    $spy = new SpyTransport();
    $payload = fixtureApiPayload('email_single.json');
    $spy->queue(new ApiResponse(4, $payload['count'], $payload['values']));

    makeEmailApi($spy)->add(42, 'new@example.org');

    expect($spy->calls[0]['params']['values'])->not->toHaveKey('on_hold');
});

it('add returns a hydrated Email DTO', function (): void {
    $spy = new SpyTransport();
    $payload = fixtureApiPayload('email_single.json');
    $spy->queue(new ApiResponse(4, $payload['count'], $payload['values']));

    $email = makeEmailApi($spy)->add(42, 'new@example.org', isPrimary: true);

    expect($email)->toBeInstanceOf(Email::class)
        ->and($email->id)->toBe(103);
});

it('remove sends action=delete with where id = $id', function (): void {
    $spy = new SpyTransport();

    makeEmailApi($spy)->remove(101);

    expect($spy->calls[0]['action'])->toBe('delete')
        ->and($spy->calls[0]['params']['where'])->toBe([['id', '=', 101]]);
});

it('getFields sends entity=Email and action=getfields', function (): void {
    $spy = new SpyTransport();

    makeEmailApi($spy)->getFields();

    expect($spy->calls[0]['entity'])->toBe('Email')
        ->and($spy->calls[0]['action'])->toBe('getfields');
});

it('getActions sends entity=Email and action=getactions', function (): void {
    $spy = new SpyTransport();

    makeEmailApi($spy)->getActions();

    expect($spy->calls[0]['entity'])->toBe('Email')
        ->and($spy->calls[0]['action'])->toBe('getactions');
});

it('updateById sends action=update and returns hydrated Email', function (): void {
    $spy = new SpyTransport();
    $payload = fixtureApiPayload('email_single.json');
    $spy->queue(new ApiResponse(4, $payload['count'], $payload['values']));

    $email = makeEmailApi($spy)->updateById(101, ['email' => 'updated@example.org']);

    expect($email)->toBeInstanceOf(Email::class)
        ->and($spy->calls[0]['action'])->toBe('update')
        ->and($spy->calls[0]['params']['values'])->toBe(['email' => 'updated@example.org']);
});

it('add throws ValidationException when create returns no records', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 0, []));

    expect(fn() => makeEmailApi($spy)->add(42, 'x@example.org'))
        ->toThrow(ValidationException::class, 'Email.create returned no records.');
});
