<?php

declare(strict_types=1);

use Woduda\CiviCRM\Exception\ValidationException;
use Woduda\CiviCRM\Query\GetQuery;
use Woduda\CiviCRM\Query\Operator;

it('builds the correct where leaf for every operator', function (Operator $op): void {
    $query = $op->requiresValue()
        ? GetQuery::new()->where('field', $op, 'val')
        : GetQuery::new()->where('field', $op);

    $expected = $op->requiresValue()
        ? ['field', $op->value, 'val']
        : ['field', $op->value];

    expect($query->toParams()['where'])->toBe([$expected]);
})->with(array_map(static fn(Operator $op): array => [$op], Operator::cases()));

it('produces a two-element leaf for unary operators', function (): void {
    expect(GetQuery::new()->whereNull('deleted_date')->toParams()['where'])
        ->toBe([['deleted_date', 'IS NULL']]);

    expect(GetQuery::new()->where('x', Operator::IsNotNull)->toParams()['where'])
        ->toBe([['x', 'IS NOT NULL']]);
});

it('builds an IN leaf via whereIn', function (): void {
    expect(GetQuery::new()->whereIn('id', [1, 2, 3])->toParams()['where'])
        ->toBe([['id', 'IN', [1, 2, 3]]]);
});

it('replaces select but appends with addSelect', function (): void {
    expect(GetQuery::new()->select('a')->select('b')->toParams()['select'])->toBe(['b']);
    expect(GetQuery::new()->select('a')->addSelect('b', 'c')->toParams()['select'])->toBe(['a', 'b', 'c']);
});

it('normalizes and validates the order direction', function (): void {
    expect(GetQuery::new()->orderBy('name')->toParams()['orderBy'])->toBe(['name' => 'ASC']);
    expect(GetQuery::new()->orderBy('name', 'desc')->toParams()['orderBy'])->toBe(['name' => 'DESC']);

    expect(fn(): GetQuery => GetQuery::new()->orderBy('name', 'sideways'))
        ->toThrow(ValidationException::class);
});

describe('orWhere grouping (Laravel-style)', function (): void {
    $a = ['first_name', '=', 'Jane'];
    $b = ['first_name', '=', 'John'];
    $c = ['last_name', '=', 'Doe'];

    it('keeps consecutive where clauses as a flat AND list', function () use ($a, $c): void {
        $where = GetQuery::new()
            ->where('first_name', Operator::Equals, 'Jane')
            ->where('last_name', Operator::Equals, 'Doe')
            ->toParams()['where'];

        expect($where)->toBe([$a, $c]);
    });

    it('wraps a single orWhere with its predecessor', function () use ($a, $b): void {
        $where = GetQuery::new()
            ->where('first_name', Operator::Equals, 'Jane')
            ->orWhere('first_name', Operator::Equals, 'John')
            ->toParams()['where'];

        expect($where)->toBe([['OR', [$a, $b]]]);
    });

    it('closes the OR group when a following where is AND', function () use ($a, $b, $c): void {
        $where = GetQuery::new()
            ->where('first_name', Operator::Equals, 'Jane')
            ->orWhere('first_name', Operator::Equals, 'John')
            ->where('last_name', Operator::Equals, 'Doe')
            ->toParams()['where'];

        expect($where)->toBe([['OR', [$a, $b]], $c]);
    });

    it('extends the OR group with further orWhere clauses', function () use ($a, $b): void {
        $c = ['first_name', '=', 'Jack'];
        $where = GetQuery::new()
            ->where('first_name', Operator::Equals, 'Jane')
            ->orWhere('first_name', Operator::Equals, 'John')
            ->orWhere('first_name', Operator::Equals, 'Jack')
            ->toParams()['where'];

        expect($where)->toBe([['OR', [$a, $b, $c]]]);
    });

    it('treats a leading orWhere as a plain where', function () use ($a): void {
        $where = GetQuery::new()
            ->orWhere('first_name', Operator::Equals, 'Jane')
            ->toParams()['where'];

        expect($where)->toBe([$a]);
    });

    it('opens a new OR group from the latest AND clause', function () use ($a, $c): void {
        $smith = ['last_name', '=', 'Smith'];
        $where = GetQuery::new()
            ->where('first_name', Operator::Equals, 'Jane')
            ->where('last_name', Operator::Equals, 'Doe')
            ->orWhere('last_name', Operator::Equals, 'Smith')
            ->toParams()['where'];

        expect($where)->toBe([$a, ['OR', [$c, $smith]]]);
    });

    it('resumes AND clauses after an extended OR group', function () use ($a, $b, $c): void {
        $jack = ['first_name', '=', 'Jack'];
        $where = GetQuery::new()
            ->where('first_name', Operator::Equals, 'Jane')
            ->orWhere('first_name', Operator::Equals, 'John')
            ->orWhere('first_name', Operator::Equals, 'Jack')
            ->where('last_name', Operator::Equals, 'Doe')
            ->toParams()['where'];

        expect($where)->toBe([['OR', [$a, $b, $jack]], $c]);
    });
});

it('accumulates multiple orderBy clauses', function (): void {
    expect(GetQuery::new()->orderBy('a')->orderBy('b', 'DESC')->toParams()['orderBy'])
        ->toBe(['a' => 'ASC', 'b' => 'DESC']);
});

it('builds a unary having clause without a value', function (): void {
    expect(GetQuery::new()->having('custom_flag', Operator::IsNull)->toParams()['having'])
        ->toBe([['custom_flag', 'IS NULL']]);
});

it('accumulates multiple having clauses', function (): void {
    $having = GetQuery::new()
        ->having('row_count', Operator::GreaterThan, 1)
        ->having('total', Operator::LessThan, 100)
        ->toParams()['having'];

    expect($having)->toBe([['row_count', '>', 1], ['total', '<', 100]]);
});

it('assembles the full params and omits empty/default keys', function (): void {
    $params = GetQuery::new()
        ->select('id', 'display_name')
        ->where('contact_type', Operator::Equals, 'Individual')
        ->orderBy('display_name', 'DESC')
        ->limit(10)
        ->offset(20)
        ->groupBy('contact_type')
        ->having('row_count', Operator::GreaterThan, 1)
        ->toParams();

    expect($params)->toBe([
        'select' => ['id', 'display_name'],
        'where' => [['contact_type', '=', 'Individual']],
        'orderBy' => ['display_name' => 'DESC'],
        'limit' => 10,
        'offset' => 20,
        'groupBy' => ['contact_type'],
        'having' => [['row_count', '>', 1]],
    ]);
});

it('returns empty params for an empty query and omits a zero offset', function (): void {
    expect(GetQuery::new()->toParams())->toBe([]);
    expect(GetQuery::new()->offset(0)->toParams())->toBe([]);
});

it('never mutates the original instance', function (): void {
    $base = GetQuery::new();
    $modified = $base->select('id')->where('a', Operator::Equals, 1)->limit(5);

    expect($base->toParams())->toBe([])
        ->and($modified->toParams())->toBe([
            'select' => ['id'],
            'where' => [['a', '=', 1]],
            'limit' => 5,
        ]);
});

it('returns a distinct instance from every with-er', function (): void {
    $steps = [];
    $q = GetQuery::new();
    $steps[] = $q;
    $steps[] = $q = $q->select('id');
    $steps[] = $q = $q->where('a', Operator::Equals, 1);
    $steps[] = $q = $q->orWhere('a', Operator::Equals, 2);
    $steps[] = $q = $q->orderBy('a');
    $steps[] = $q = $q->limit(1);
    $steps[] = $q = $q->offset(1);
    $steps[] = $q = $q->groupBy('a');
    $steps[] = $q->having('a', Operator::GreaterThan, 0);

    foreach ($steps as $i => $left) {
        foreach ($steps as $j => $right) {
            if ($i < $j) {
                expect($left)->not->toBe($right);
            }
        }
    }
});

it('produces deterministic params for a repeated sequence', function (string $field, Operator $op): void {
    $build = fn(): GetQuery => GetQuery::new()
        ->select('id')
        ->where($field, $op, 'v')
        ->orWhere($field, Operator::Equals, 'w')
        ->orderBy($field, 'DESC')
        ->limit(3)
        ->offset(2);

    expect($build()->toParams())->toBe($build()->toParams());
})->with([
    ['first_name', Operator::Equals],
    ['age', Operator::GreaterThan],
    ['tags', Operator::Like],
    ['status', Operator::NotEquals],
]);
