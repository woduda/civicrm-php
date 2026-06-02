<?php

declare(strict_types=1);

use Woduda\CiviCRM\Query\ActionRequest;
use Woduda\CiviCRM\Query\ChainBuilder;
use Woduda\CiviCRM\Query\GetQuery;
use Woduda\CiviCRM\Query\Operator;

it('builds a create action', function (): void {
    $request = ActionRequest::create('Contact', ['first_name' => 'Jane']);

    expect($request->entity)->toBe('Contact')
        ->and($request->action)->toBe('create')
        ->and($request->toParams())->toBe(['values' => ['first_name' => 'Jane']]);
});

it('builds an update action with where', function (): void {
    $request = ActionRequest::update('Contact', ['first_name' => 'Jane'], [['id', '=', 42]]);

    expect($request->action)->toBe('update')
        ->and($request->toParams())->toBe([
            'values' => ['first_name' => 'Jane'],
            'where' => [['id', '=', 42]],
        ]);
});

it('builds a save action emitting records', function (): void {
    $request = ActionRequest::save('Contact', [['first_name' => 'A'], ['first_name' => 'B']]);

    expect($request->action)->toBe('save')
        ->and($request->toParams())->toBe([
            'records' => [['first_name' => 'A'], ['first_name' => 'B']],
        ]);
});

it('builds a delete action', function (): void {
    $request = ActionRequest::delete('Contact', [['id', '=', 42]]);

    expect($request->action)->toBe('delete')
        ->and($request->toParams())->toBe(['where' => [['id', '=', 42]]]);
});

it('adds a limit via withLimit', function (): void {
    $request = ActionRequest::delete('Contact', [['is_deleted', '=', 1]])->withLimit(100);

    expect($request->toParams())->toBe([
        'where' => [['is_deleted', '=', 1]],
        'limit' => 100,
    ]);
});

it('chains an ActionRequest sub using its own entity and action', function (): void {
    $request = ActionRequest::create('Contact', ['first_name' => 'Jane'])
        ->withChain('email', ActionRequest::create('Email', ['email' => 'jane@example.org', 'contact_id' => '$id']));

    expect($request->toParams()['chain'])->toBe([
        'email' => ['Email', 'create', ['values' => ['email' => 'jane@example.org', 'contact_id' => '$id']]],
    ]);
});

it('chains a GetQuery sub as a get on the parent entity', function (): void {
    $request = ActionRequest::create('Contact', ['first_name' => 'Jane'])
        ->withChain('self', GetQuery::new()->select('id'));

    expect($request->toParams()['chain'])->toBe([
        'self' => ['Contact', 'get', ['select' => ['id']]],
    ]);
});

it('merges a ChainBuilder via withChainBuilder', function (): void {
    $request = ActionRequest::create('Contact', ['first_name' => 'Jane'])
        ->withChainBuilder(ChainBuilder::new()->create('email', 'Email', ['email' => 'jane@example.org']));

    expect($request->toParams()['chain'])->toBe([
        'email' => ['Email', 'create', ['values' => ['email' => 'jane@example.org']]],
    ]);
});

it('accumulates multiple chained calls in order', function (): void {
    $request = ActionRequest::create('Contact', ['x' => 1])
        ->withChain('email', ActionRequest::create('Email', ['email' => 'a']))
        ->withChain('phone', ActionRequest::create('Phone', ['phone' => '1']));

    expect($request->toParams()['chain'])->toBe([
        'email' => ['Email', 'create', ['values' => ['email' => 'a']]],
        'phone' => ['Phone', 'create', ['values' => ['phone' => '1']]],
    ]);
});

it('merges a ChainBuilder on top of existing chains', function (): void {
    $request = ActionRequest::create('Contact', ['x' => 1])
        ->withChain('email', ActionRequest::create('Email', ['email' => 'a']))
        ->withChainBuilder(ChainBuilder::new()->create('phone', 'Phone', ['phone' => '1']));

    expect($request->toParams()['chain'])->toBe([
        'email' => ['Email', 'create', ['values' => ['email' => 'a']]],
        'phone' => ['Phone', 'create', ['values' => ['phone' => '1']]],
    ]);
});

it('does not mutate the original when chaining', function (): void {
    $base = ActionRequest::create('Contact', ['first_name' => 'Jane']);
    $chained = $base->withChain('email', ActionRequest::create('Email', ['email' => 'x']));

    expect($base->toParams())->not->toHaveKey('chain')
        ->and($base)->not->toBe($chained);
});

it('combines having-free get sub-params correctly', function (): void {
    $request = ActionRequest::update('Contact', ['x' => 1], [['id', '=', 1]])
        ->withLimit(5)
        ->withChain('related', GetQuery::new()->where('contact_id', Operator::Equals, '$id'));

    expect($request->toParams())->toBe([
        'values' => ['x' => 1],
        'where' => [['id', '=', 1]],
        'limit' => 5,
        'chain' => [
            'related' => ['Contact', 'get', ['where' => [['contact_id', '=', '$id']]]],
        ],
    ]);
});
