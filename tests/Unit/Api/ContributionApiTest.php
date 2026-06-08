<?php

declare(strict_types=1);

use Woduda\CiviCRM\Api\ContributionApi;
use Woduda\CiviCRM\Api\FinancialTypeResolver;
use Woduda\CiviCRM\Entity\Contribution;
use Woduda\CiviCRM\Entity\ContributionStatus;
use Woduda\CiviCRM\Entity\ContributionTotals;
use Woduda\CiviCRM\Exception\ValidationException;
use Woduda\CiviCRM\Query\GetQuery;
use Woduda\CiviCRM\Query\Operator;
use Woduda\CiviCRM\Result\ApiResponse;
use Woduda\CiviCRM\Result\Result;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeContributionApi(SpyTransport $spy): ContributionApi
{
    return new ContributionApi($spy, new FinancialTypeResolver($spy));
}

function singleContributionResponse(): ApiResponse
{
    return new ApiResponse(4, 1, [fixtureFirstRow('contribution-single.json')]);
}

/**
 * Narrows params['values'] to an array accessible by string or int keys.
 *
 * @return array<mixed, mixed>
 */
function callValues(SpyTransport $spy, int $callIndex): array
{
    $val = $spy->calls[$callIndex]['params']['values'] ?? [];

    return is_array($val) ? $val : [];
}

/**
 * Narrows params['where'] to list<list<mixed>>.
 *
 * @return list<list<mixed>>
 */
function callWhere(SpyTransport $spy, int $callIndex): array
{
    $where = $spy->calls[$callIndex]['params']['where'] ?? [];

    if (! is_array($where)) {
        return [];
    }

    /** @var list<list<mixed>> */
    return array_values(array_filter($where, is_array(...)));
}

// ---------------------------------------------------------------------------
// get
// ---------------------------------------------------------------------------

it('get sends entity=Contribution, action=get with compiled query params', function (): void {
    $spy = new SpyTransport();
    $query = GetQuery::new()->where('currency', Operator::Equals, 'PLN')->limit(5);

    makeContributionApi($spy)->get($query);

    expect($spy->calls[0]['entity'])->toBe('Contribution')
        ->and($spy->calls[0]['action'])->toBe('get')
        ->and($spy->calls[0]['params'])->toBe($query->toParams());
});

it('get returns a Result of Contribution DTOs', function (): void {
    $spy = new SpyTransport();
    $spy->queue(singleContributionResponse());

    $result = makeContributionApi($spy)->get(GetQuery::new());

    expect($result)->toBeInstanceOf(Result::class)
        ->and($result->count())->toBe(1)
        ->and($result->first())->toBeInstanceOf(Contribution::class)
        ->and($result->first()?->id)->toBe(99);
});

// ---------------------------------------------------------------------------
// getById
// ---------------------------------------------------------------------------

it('getById sends where id=X and limit=1', function (): void {
    $spy = new SpyTransport();

    makeContributionApi($spy)->getById(99);

    expect($spy->calls[0]['params']['where'])->toBe([['id', '=', 99]])
        ->and($spy->calls[0]['params']['limit'])->toBe(1);
});

it('getById returns a hydrated Contribution when found', function (): void {
    $spy = new SpyTransport();
    $spy->queue(singleContributionResponse());

    $contribution = makeContributionApi($spy)->getById(99);

    expect($contribution)->toBeInstanceOf(Contribution::class)
        ->and($contribution?->id)->toBe(99);
});

it('getById returns null when not found', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 0, []));

    $contribution = makeContributionApi($spy)->getById(999);

    expect($contribution)->toBeNull();
});

// ---------------------------------------------------------------------------
// create (low-level)
// ---------------------------------------------------------------------------

it('create sends entity=Contribution, action=create with values verbatim', function (): void {
    $spy = new SpyTransport();
    $spy->queue(singleContributionResponse());

    $values = [
        'contact_id' => 42,
        'total_amount' => 100.0,
        'currency' => 'EUR',
        'financial_type_id' => 1,
        'contribution_status_id:name' => 'Completed',
    ];

    makeContributionApi($spy)->create($values);

    expect($spy->calls[0]['entity'])->toBe('Contribution')
        ->and($spy->calls[0]['action'])->toBe('create')
        ->and($spy->calls[0]['params']['values'])->toBe($values);
});

it('create returns a hydrated Contribution DTO', function (): void {
    $spy = new SpyTransport();
    $spy->queue(singleContributionResponse());

    $contribution = makeContributionApi($spy)->create(['contact_id' => 42, 'total_amount' => 100.0, 'financial_type_id' => 1]);

    expect($contribution)->toBeInstanceOf(Contribution::class)
        ->and($contribution->id)->toBe(99);
});

it('create throws ValidationException when API returns empty result', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 0, []));

    expect(fn() => makeContributionApi($spy)->create(['financial_type_id' => 1]))
        ->toThrow(ValidationException::class);
});

// ---------------------------------------------------------------------------
// recordOneTime
// ---------------------------------------------------------------------------

it('recordOneTime resolves financial type name to id before creating', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 1, 'name' => 'Donation']]));
    $spy->queue(singleContributionResponse());

    makeContributionApi($spy)->recordOneTime(42, 500.0);

    expect($spy->calls[0]['entity'])->toBe('FinancialType')
        ->and($spy->calls[1]['entity'])->toBe('Contribution')
        ->and(callValues($spy, 1)['financial_type_id'])->toBe(1);
});

it('recordOneTime uses PLN currency and Completed status by default', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 1, 'name' => 'Donation']]));
    $spy->queue(singleContributionResponse());

    makeContributionApi($spy)->recordOneTime(42, 500.0);

    $values = callValues($spy, 1);
    expect($values['currency'])->toBe('PLN')
        ->and($values['contribution_status_id:name'])->toBe('Completed');
});

it('recordOneTime sets contact_id and total_amount from arguments', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 1, 'name' => 'Donation']]));
    $spy->queue(singleContributionResponse());

    makeContributionApi($spy)->recordOneTime(42, 250.0);

    $values = callValues($spy, 1);
    expect($values['contact_id'])->toBe(42)
        ->and($values['total_amount'])->toBe(250.0);
});

it('recordOneTime accepts all optional overrides', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 2, 'name' => 'Member Dues']]));
    $spy->queue(singleContributionResponse());

    $date = new DateTimeImmutable('2025-12-31 10:00:00');

    makeContributionApi($spy)->recordOneTime(
        contactId: 7,
        amount: 99.0,
        currency: 'EUR',
        receiveDate: $date,
        financialType: 'Member Dues',
        status: ContributionStatus::Pending,
        source: 'Bank transfer',
        extra: ['trxn_id' => 'T-001'],
    );

    $values = callValues($spy, 1);
    expect($values['contact_id'])->toBe(7)
        ->and($values['total_amount'])->toBe(99.0)
        ->and($values['currency'])->toBe('EUR')
        ->and($values['receive_date'])->toBe('2025-12-31 10:00:00')
        ->and($values['financial_type_id'])->toBe(2)
        ->and($values['contribution_status_id:name'])->toBe('Pending')
        ->and($values['source'])->toBe('Bank transfer')
        ->and($values['trxn_id'])->toBe('T-001');
});

it('recordOneTime sets receive_date to today when null', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 1, 'name' => 'Donation']]));
    $spy->queue(singleContributionResponse());

    makeContributionApi($spy)->recordOneTime(42, 100.0, receiveDate: null);

    $sentDate = callValues($spy, 1)['receive_date'];
    $todayDate = (new DateTimeImmutable())->format('Y-m-d');

    expect($sentDate)->toBeString()
        ->and(is_string($sentDate) ? substr($sentDate, 0, 10) : '')->toBe($todayDate);
});

it('recordOneTime omits source when null', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 1, 'name' => 'Donation']]));
    $spy->queue(singleContributionResponse());

    makeContributionApi($spy)->recordOneTime(42, 100.0);

    expect($spy->calls[1]['params']['values'])->not->toHaveKey('source');
});

it('recordOneTime returns a hydrated Contribution DTO', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['id' => 1, 'name' => 'Donation']]));
    $spy->queue(singleContributionResponse());

    $contribution = makeContributionApi($spy)->recordOneTime(42, 500.0);

    expect($contribution)->toBeInstanceOf(Contribution::class)
        ->and($contribution->id)->toBe(99);
});

// ---------------------------------------------------------------------------
// forContact
// ---------------------------------------------------------------------------

it('forContact sends where contact_id=X and orderBy receive_date DESC', function (): void {
    $spy = new SpyTransport();

    makeContributionApi($spy)->forContact(42);

    expect($spy->calls[0]['entity'])->toBe('Contribution')
        ->and($spy->calls[0]['action'])->toBe('get')
        ->and($spy->calls[0]['params']['where'])->toContain(['contact_id', '=', 42])
        ->and($spy->calls[0]['params']['orderBy'])->toBe(['receive_date' => 'DESC']);
});

it('forContact returns a Result of Contribution DTOs', function (): void {
    $spy = new SpyTransport();
    $payload = fixtureApiPayload('contribution-many.json');
    $spy->queue(new ApiResponse(4, $payload['count'], $payload['values']));

    $result = makeContributionApi($spy)->forContact(42);

    expect($result)->toBeInstanceOf(Result::class)
        ->and($result->count())->toBe(4)
        ->and($result->first())->toBeInstanceOf(Contribution::class);
});

// ---------------------------------------------------------------------------
// totalsForContact
// ---------------------------------------------------------------------------

it('totalsForContact makes exactly two transport calls', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['SUM(total_amount)' => 925.0, 'COUNT(id)' => 4, 'MIN(receive_date)' => '2023-03-20 14:30:00', 'MAX(receive_date)' => '2026-05-01 12:00:00']]));
    $spy->queue(new ApiResponse(4, 1, [['SUM(total_amount)' => 750.0, 'COUNT(id)' => 2]]));

    makeContributionApi($spy)->totalsForContact(42);

    expect($spy->calls)->toHaveCount(2);
});

it('totalsForContact first call has lifetime aggregate selects', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['SUM(total_amount)' => 0.0, 'COUNT(id)' => 0, 'MIN(receive_date)' => null, 'MAX(receive_date)' => null]]));
    $spy->queue(new ApiResponse(4, 1, [['SUM(total_amount)' => 0.0, 'COUNT(id)' => 0]]));

    makeContributionApi($spy)->totalsForContact(42);

    $select = $spy->calls[0]['params']['select'];
    expect($select)->toContain('SUM(total_amount)')
        ->and($select)->toContain('COUNT(id)')
        ->and($select)->toContain('MIN(receive_date)')
        ->and($select)->toContain('MAX(receive_date)');
});

it('totalsForContact second call filters by receive_date >= 12 months ago', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [['SUM(total_amount)' => 0.0, 'COUNT(id)' => 0, 'MIN(receive_date)' => null, 'MAX(receive_date)' => null]]));
    $spy->queue(new ApiResponse(4, 1, [['SUM(total_amount)' => 0.0, 'COUNT(id)' => 0]]));

    makeContributionApi($spy)->totalsForContact(42);

    $where = callWhere($spy, 1);
    $dateClauses = array_values(array_filter($where, fn($c): bool => ($c[0] ?? '') === 'receive_date'));
    expect($dateClauses)->not->toBeEmpty();

    $clause = $dateClauses[0];
    $sentDate = is_string($clause[2] ?? null)
        ? DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $clause[2])
        : false;
    $expectedDate = (new DateTimeImmutable())->modify('-12 months');

    expect($sentDate)->not->toBeFalse()
        ->and($sentDate !== false ? $sentDate->format('Y-m-d') : '')->toBe($expectedDate->format('Y-m-d'));
});

it('totalsForContact maps lifetime aggregates to ContributionTotals', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [[
        'SUM(total_amount)' => 925.0,
        'COUNT(id)' => 4,
        'MIN(receive_date)' => '2023-03-20 14:30:00',
        'MAX(receive_date)' => '2026-05-01 12:00:00',
    ]]));
    $spy->queue(new ApiResponse(4, 1, [['SUM(total_amount)' => 750.0, 'COUNT(id)' => 2]]));

    $totals = makeContributionApi($spy)->totalsForContact(42, 'PLN');

    expect($totals)->toBeInstanceOf(ContributionTotals::class)
        ->and($totals->lifetimeTotal)->toBe(925.0)
        ->and($totals->lifetimeCount)->toBe(4)
        ->and($totals->last12MonthsTotal)->toBe(750.0)
        ->and($totals->last12MonthsCount)->toBe(2)
        ->and($totals->currency)->toBe('PLN');
});

it('totalsForContact parses first and last contribution dates', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, [[
        'SUM(total_amount)' => 100.0,
        'COUNT(id)' => 1,
        'MIN(receive_date)' => '2023-03-20 14:30:00',
        'MAX(receive_date)' => '2026-05-01 12:00:00',
    ]]));
    $spy->queue(new ApiResponse(4, 1, [['SUM(total_amount)' => 100.0, 'COUNT(id)' => 1]]));

    $totals = makeContributionApi($spy)->totalsForContact(42);

    expect($totals->firstContributionAt)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($totals->firstContributionAt?->format('Y-m-d'))->toBe('2023-03-20')
        ->and($totals->lastContributionAt)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($totals->lastContributionAt?->format('Y-m-d'))->toBe('2026-05-01');
});

it('totalsForContact returns zeros and null dates when contact has no contributions', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 0, []));
    $spy->queue(new ApiResponse(4, 0, []));

    $totals = makeContributionApi($spy)->totalsForContact(42);

    expect($totals->lifetimeTotal)->toBe(0.0)
        ->and($totals->lifetimeCount)->toBe(0)
        ->and($totals->last12MonthsTotal)->toBe(0.0)
        ->and($totals->last12MonthsCount)->toBe(0)
        ->and($totals->firstContributionAt)->toBeNull()
        ->and($totals->lastContributionAt)->toBeNull();
});

it('totalsForContact filters both calls by contact_id and currency', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 0, []));
    $spy->queue(new ApiResponse(4, 0, []));

    makeContributionApi($spy)->totalsForContact(42, 'EUR');

    foreach ([0, 1] as $callIndex) {
        $where = $spy->calls[$callIndex]['params']['where'];
        expect($where)->toContain(['contact_id', '=', 42])
            ->and($where)->toContain(['currency', '=', 'EUR']);
    }
});

// ---------------------------------------------------------------------------
// completedSince
// ---------------------------------------------------------------------------

it('completedSince sends where status=Completed and receive_date >= since', function (): void {
    $spy = new SpyTransport();
    $since = new DateTimeImmutable('2026-01-01 00:00:00');

    makeContributionApi($spy)->completedSince($since);

    $where = $spy->calls[0]['params']['where'];
    expect($where)->toContain(['contribution_status_id:name', '=', 'Completed'])
        ->and($where)->toContain(['receive_date', '>=', '2026-01-01 00:00:00']);
});

it('completedSince orders results by receive_date DESC', function (): void {
    $spy = new SpyTransport();

    makeContributionApi($spy)->completedSince(new DateTimeImmutable());

    expect($spy->calls[0]['params']['orderBy'])->toBe(['receive_date' => 'DESC']);
});

it('completedSince returns a Result of Contribution DTOs', function (): void {
    $spy = new SpyTransport();
    $spy->queue(singleContributionResponse());

    $result = makeContributionApi($spy)->completedSince(new DateTimeImmutable('2026-01-01'));

    expect($result)->toBeInstanceOf(Result::class)
        ->and($result->first())->toBeInstanceOf(Contribution::class);
});

// ---------------------------------------------------------------------------
// markCompleted
// ---------------------------------------------------------------------------

it('markCompleted sends Contribution.update with status=Completed', function (): void {
    $spy = new SpyTransport();

    makeContributionApi($spy)->markCompleted(99);

    expect($spy->calls[0]['entity'])->toBe('Contribution')
        ->and($spy->calls[0]['action'])->toBe('update')
        ->and($spy->calls[0]['params']['where'])->toBe([['id', '=', 99]])
        ->and(callValues($spy, 0)['contribution_status_id:name'])->toBe('Completed');
});

it('markCompleted includes trxn_id when provided', function (): void {
    $spy = new SpyTransport();

    makeContributionApi($spy)->markCompleted(99, 'TXN-001');

    expect(callValues($spy, 0)['trxn_id'])->toBe('TXN-001');
});

it('markCompleted omits trxn_id when null', function (): void {
    $spy = new SpyTransport();

    makeContributionApi($spy)->markCompleted(99);

    expect($spy->calls[0]['params']['values'])->not->toHaveKey('trxn_id');
});

it('markCompleted includes receive_date when provided', function (): void {
    $spy = new SpyTransport();
    $date = new DateTimeImmutable('2026-06-01 10:00:00');

    makeContributionApi($spy)->markCompleted(99, null, $date);

    expect(callValues($spy, 0)['receive_date'])->toBe('2026-06-01 10:00:00');
});

it('markCompleted omits receive_date when null', function (): void {
    $spy = new SpyTransport();

    makeContributionApi($spy)->markCompleted(99);

    expect($spy->calls[0]['params']['values'])->not->toHaveKey('receive_date');
});

it('markCompleted makes exactly one transport call', function (): void {
    $spy = new SpyTransport();

    makeContributionApi($spy)->markCompleted(99, 'TXN');

    expect($spy->calls)->toHaveCount(1);
});

// ---------------------------------------------------------------------------
// refund
// ---------------------------------------------------------------------------

it('refund sends Contribution.update with status=Refunded', function (): void {
    $spy = new SpyTransport();

    makeContributionApi($spy)->refund(99);

    expect($spy->calls[0]['entity'])->toBe('Contribution')
        ->and($spy->calls[0]['action'])->toBe('update')
        ->and(callValues($spy, 0)['contribution_status_id:name'])->toBe('Refunded')
        ->and($spy->calls[0]['params']['where'])->toBe([['id', '=', 99]]);
});

it('refund without reason makes exactly one transport call', function (): void {
    $spy = new SpyTransport();

    makeContributionApi($spy)->refund(99);

    expect($spy->calls)->toHaveCount(1);
});

it('refund with reason fetches the contribution and creates a Follow Up activity', function (): void {
    $spy = new SpyTransport();
    $spy->queue(new ApiResponse(4, 1, []));
    $spy->queue(new ApiResponse(4, 1, [['id' => 99, 'contact_id' => 42]]));
    $spy->queue(new ApiResponse(4, 1, []));

    makeContributionApi($spy)->refund(99, 'Duplicate payment');

    expect($spy->calls)->toHaveCount(3)
        ->and($spy->calls[1]['entity'])->toBe('Contribution')
        ->and($spy->calls[1]['action'])->toBe('get')
        ->and($spy->calls[2]['entity'])->toBe('Activity')
        ->and($spy->calls[2]['action'])->toBe('create')
        ->and(callValues($spy, 2)['details'])->toBe('Duplicate payment')
        ->and(callValues($spy, 2)['subject'])->toBe('Refund')
        ->and(callValues($spy, 2)['source_contact_id'])->toBe(42);
});

// ---------------------------------------------------------------------------
// getFields / getActions
// ---------------------------------------------------------------------------

it('getFields sends entity=Contribution and action=getfields', function (): void {
    $spy = new SpyTransport();

    makeContributionApi($spy)->getFields();

    expect($spy->calls[0]['entity'])->toBe('Contribution')
        ->and($spy->calls[0]['action'])->toBe('getfields');
});

it('getActions sends entity=Contribution and action=getactions', function (): void {
    $spy = new SpyTransport();

    makeContributionApi($spy)->getActions();

    expect($spy->calls[0]['entity'])->toBe('Contribution')
        ->and($spy->calls[0]['action'])->toBe('getactions');
});
