<?php

declare(strict_types=1);

use Woduda\CiviCRM\Query\ChainBuilder;
use Woduda\CiviCRM\Query\GetQuery;
use Woduda\CiviCRM\Query\Operator;

it('builds a create chain entry', function (): void {
    $chain = ChainBuilder::new()
        ->create('email', 'Email', ['email' => 'jane@example.org', 'contact_id' => '$id'])
        ->toParams();

    expect($chain)->toBe([
        'email' => ['Email', 'create', ['values' => ['email' => 'jane@example.org', 'contact_id' => '$id']]],
    ]);
});

it('builds a get chain entry from a GetQuery', function (): void {
    $chain = ChainBuilder::new()
        ->get('emails', 'Email', GetQuery::new()->where('contact_id', Operator::Equals, '$id'))
        ->toParams();

    expect($chain)->toBe([
        'emails' => ['Email', 'get', ['where' => [['contact_id', '=', '$id']]]],
    ]);
});

it('supports a raw params array and an index via add', function (): void {
    $chain = ChainBuilder::new()
        ->add('tag', 'EntityTag', 'create', ['values' => ['tag_id' => 3]], '0')
        ->toParams();

    expect($chain)->toBe([
        'tag' => ['EntityTag', 'create', ['values' => ['tag_id' => 3]], '0'],
    ]);
});

it('resolves a GetQuery passed to add', function (): void {
    $chain = ChainBuilder::new()
        ->add('latest', 'Activity', 'get', GetQuery::new()->limit(1))
        ->toParams();

    expect($chain)->toBe([
        'latest' => ['Activity', 'get', ['limit' => 1]],
    ]);
});

it('accumulates multiple entries immutably', function (): void {
    $base = ChainBuilder::new();
    $extended = $base
        ->create('email', 'Email', ['email' => 'x'])
        ->create('phone', 'Phone', ['phone' => '123']);

    expect($base->toParams())->toBe([])
        ->and(array_keys($extended->toParams()))->toBe(['email', 'phone']);
});
