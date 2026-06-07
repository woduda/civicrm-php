<?php

declare(strict_types=1);

use Woduda\CiviCRM\Api\PhoneApi;
use Woduda\CiviCRM\Exception\ValidationException;
use Woduda\CiviCRM\Entity\Phone;
use Woduda\CiviCRM\Query\GetQuery;
use Woduda\CiviCRM\Query\Operator;
use Woduda\CiviCRM\Result\ApiResponse;

function makePhoneApi(SpyTransport $spy): PhoneApi
{
    return new PhoneApi($spy);
}

it('get returns a Result of Phone DTOs from the transport response', function (): void {
    $spy = new SpyTransport();
    $payload = fixtureApiPayload('phone_single.json');
    $spy->queue(new ApiResponse(4, $payload['count'], $payload['values']));

    $result = makePhoneApi($spy)->get(GetQuery::new());

    expect($result->first())->toBeInstanceOf(Phone::class)
        ->and($result->first()?->phone)->toBe('+48987654321');
});

it('forContact sends get with contact_id where and is_primary order', function (): void {
    $spy = new SpyTransport();
    $payload = fixtureApiPayload('phones_for_contact.json');
    $spy->queue(new ApiResponse(4, $payload['count'], $payload['values']));

    makePhoneApi($spy)->forContact(42);

    expect($spy->calls[0]['entity'])->toBe('Phone')
        ->and($spy->calls[0]['params']['where'])->toBe(
            GetQuery::new()->where('contact_id', Operator::Equals, 42)->toParams()['where'],
        )
        ->and($spy->calls[0]['params']['orderBy'])->toBe(['is_primary' => 'DESC']);
});

it('primary returns the phone marked is_primary', function (): void {
    $spy = new SpyTransport();
    $payload = fixtureApiPayload('phones_for_contact.json');
    $spy->queue(new ApiResponse(4, $payload['count'], $payload['values']));

    $primary = makePhoneApi($spy)->primary(42);

    expect($primary)->toBeInstanceOf(Phone::class)
        ->and($primary?->id)->toBe(201)
        ->and($primary?->isPrimary)->toBeTrue();
});

it('setPrimary sends a single update with is_primary=true', function (): void {
    $spy = new SpyTransport();

    makePhoneApi($spy)->setPrimary(201);

    expect($spy->calls)->toHaveCount(1)
        ->and($spy->calls[0]['action'])->toBe('update')
        ->and($spy->calls[0]['params']['values'])->toBe(['is_primary' => true])
        ->and($spy->calls[0]['params']['where'])->toBe([['id', '=', 201]]);
});

it('add sends action=create with phone_type when provided', function (): void {
    $spy = new SpyTransport();
    $payload = fixtureApiPayload('phone_single.json');
    $spy->queue(new ApiResponse(4, $payload['count'], $payload['values']));

    makePhoneApi($spy)->add(42, '+48987654321', 'Mobile', 'Home', isPrimary: true);

    expect($spy->calls[0]['params']['values'])->toBe([
        'contact_id' => 42,
        'phone' => '+48987654321',
        'location_type_id.name' => 'Home',
        'is_primary' => true,
        'phone_type_id.name' => 'Mobile',
    ]);
});

it('remove sends action=delete with where id = $id', function (): void {
    $spy = new SpyTransport();

    makePhoneApi($spy)->remove(201);

    expect($spy->calls[0]['action'])->toBe('delete')
        ->and($spy->calls[0]['params']['where'])->toBe([['id', '=', 201]]);
});

it('getFields sends entity=Phone and action=getfields', function (): void {
    $spy = new SpyTransport();

    makePhoneApi($spy)->getFields();

    expect($spy->calls[0]['entity'])->toBe('Phone')
        ->and($spy->calls[0]['action'])->toBe('getfields');
});

it('getActions sends entity=Phone and action=getactions', function (): void {
    $spy = new SpyTransport();

    makePhoneApi($spy)->getActions();

    expect($spy->calls[0]['entity'])->toBe('Phone')
        ->and($spy->calls[0]['action'])->toBe('getactions');
});

it('add omits phone_type when not provided', function (): void {
    $spy = new SpyTransport();
    $payload = fixtureApiPayload('phone_single.json');
    $spy->queue(new ApiResponse(4, $payload['count'], $payload['values']));

    makePhoneApi($spy)->add(42, '+48111111111');

    expect($spy->calls[0]['params']['values'])->not->toHaveKey('phone_type_id.name');
});

it('updateById sends action=update and returns hydrated Phone', function (): void {
    $spy = new SpyTransport();
    $payload = fixtureApiPayload('phone_single.json');
    $spy->queue(new ApiResponse(4, $payload['count'], $payload['values']));

    $phone = makePhoneApi($spy)->updateById(201, ['phone' => '+48999999999']);

    expect($phone)->toBeInstanceOf(Phone::class)
        ->and($spy->calls[0]['action'])->toBe('update');
});

it('primary returns null when forContact is empty', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 0, []));

    expect(makePhoneApi($spy)->primary(42))->toBeNull();
});

it('add defaults is_primary to false when not specified', function (): void {
    $spy = new SpyTransport();
    $payload = fixtureApiPayload('phone_single.json');
    $spy->queue(new ApiResponse(4, $payload['count'], $payload['values']));

    makePhoneApi($spy)->add(42, '+48999999999');

    /** @var array<string, mixed> $values */
    $values = $spy->calls[0]['params']['values'];
    expect($values['is_primary'])->toBeFalse();
});

it('primary skips non-primary phones and returns the primary one', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 2, [
        ['id' => 202, 'contact_id' => 42, 'phone' => '+48111111111', 'is_primary' => false],
        ['id' => 201, 'contact_id' => 42, 'phone' => '+48987654321', 'is_primary' => true],
    ]));

    $primary = makePhoneApi($spy)->primary(42);

    expect($primary?->id)->toBe(201);
});

it('updateById throws ValidationException when update returns no records', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 0, []));

    expect(fn() => makePhoneApi($spy)->updateById(201, ['phone' => '+48000000000']))
        ->toThrow(ValidationException::class);
});
