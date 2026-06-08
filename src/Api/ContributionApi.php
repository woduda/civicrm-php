<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Api;

use DateTimeImmutable;
use Woduda\CiviCRM\Contract\TransportInterface;
use Woduda\CiviCRM\Entity\Contribution;
use Woduda\CiviCRM\Entity\ContributionStatus;
use Woduda\CiviCRM\Entity\ContributionTotals;
use Woduda\CiviCRM\Exception\ValidationException;
use Woduda\CiviCRM\Query\ActionRequest;
use Woduda\CiviCRM\Query\GetQuery;
use Woduda\CiviCRM\Query\Operator;
use Woduda\CiviCRM\Result\Result;
use Woduda\CiviCRM\Result\TypedResult;

/**
 * Typed API for the CiviCRM `Contribution` entity.
 *
 * Two creation paths are available:
 * - {@see recordOneTime()} — convenience method that resolves the financial type name
 *   to an integer ID automatically via {@see FinancialTypeResolver}. Use this for
 *   the common case of recording a one-off donation.
 * - {@see create()} — low-level method that accepts a raw values array and sends it
 *   directly to the API. The caller is responsible for providing `financial_type_id`
 *   as an integer. Use this when you need full control over every field.
 *
 * Example:
 * ```php
 * $contributions = $client->contributions();
 *
 * $contribution = $contributions->recordOneTime(42, 500.00, 'PLN');
 * $totals = $contributions->totalsForContact(42);
 * $contributions->markCompleted($contribution->id, 'TXN-001');
 * ```
 */
final readonly class ContributionApi extends AbstractEntityApi
{
    public function __construct(
        TransportInterface $transport,
        private FinancialTypeResolver $financialTypeResolver,
    ) {
        parent::__construct($transport, 'Contribution');
    }

    /**
     * Fetches contributions matching an arbitrary query.
     *
     * @return Result<Contribution>
     *
     * Example:
     * ```php
     * $result = $api->get(GetQuery::new()->where('currency', Operator::Equals, 'PLN')->limit(10));
     * ```
     */
    public function get(GetQuery $query): Result
    {
        return TypedResult::hydrate($this->executeGet($query), Contribution::class);
    }

    /**
     * Returns a single contribution by ID, or `null` when not found.
     *
     * Example:
     * ```php
     * $contribution = $api->getById(99);
     * ```
     */
    public function getById(int $id): ?Contribution
    {
        $query = GetQuery::new()
            ->where('id', Operator::Equals, $id)
            ->limit(1);

        return TypedResult::hydrate($this->executeGet($query), Contribution::class)->first();
    }

    /**
     * Records a one-time donation with sensible defaults.
     *
     * The financial type name (e.g. `'Donation'`) is resolved to its integer ID
     * automatically. If you already have the ID, use {@see create()} directly and
     * pass `financial_type_id` in the values array.
     *
     * `$receiveDate` defaults to the current date and time when `null`.
     *
     * @param array<string, mixed> $extra Additional APIv4 fields merged into the create payload.
     *
     * @throws ValidationException When the financial type does not exist or the API returns no record.
     *
     * Example:
     * ```php
     * $contribution = $api->recordOneTime(42, 250.00, 'PLN', null, 'Donation', ContributionStatus::Completed);
     * ```
     */
    public function recordOneTime(
        int $contactId,
        float $amount,
        string $currency = 'PLN',
        ?DateTimeImmutable $receiveDate = null,
        string $financialType = 'Donation',
        ContributionStatus $status = ContributionStatus::Completed,
        ?string $source = null,
        array $extra = [],
    ): Contribution {
        $financialTypeId = $this->financialTypeResolver->resolve($financialType);

        $values = array_merge([
            'contact_id' => $contactId,
            'total_amount' => $amount,
            'currency' => $currency,
            'receive_date' => ($receiveDate ?? new DateTimeImmutable())->format('Y-m-d H:i:s'),
            'financial_type_id' => $financialTypeId,
            'contribution_status_id:name' => $status->value,
        ], $extra);

        if ($source !== null) {
            $values['source'] = $source;
        }

        return $this->create($values);
    }

    /**
     * Creates a contribution from a raw values array.
     *
     * The caller must provide `financial_type_id` as an integer. For automatic
     * resolution of the financial type name, use {@see recordOneTime()} instead.
     *
     * @param array<string, mixed> $values
     * @throws ValidationException When the API returns no record.
     *
     * Example:
     * ```php
     * $contribution = $api->create([
     *     'contact_id' => 42,
     *     'total_amount' => 100.00,
     *     'currency' => 'PLN',
     *     'financial_type_id' => 1,
     *     'contribution_status_id:name' => 'Completed',
     * ]);
     * ```
     */
    public function create(array $values): Contribution
    {
        $result = TypedResult::hydrate(
            $this->executeAction(ActionRequest::create($this->entity, $values)),
            Contribution::class,
        );

        $first = $result->first();

        if (! $first instanceof Contribution) {
            throw ValidationException::emptyApiResult($this->entity, 'create');
        }

        return $first;
    }

    /**
     * Returns all contributions for a contact, most recent first.
     *
     * @return Result<Contribution>
     *
     * Example:
     * ```php
     * $contributions = $api->forContact(42);
     * ```
     */
    public function forContact(int $contactId): Result
    {
        $query = GetQuery::new()
            ->where('contact_id', Operator::Equals, $contactId)
            ->orderBy('receive_date', 'DESC');

        return TypedResult::hydrate($this->executeGet($query), Contribution::class);
    }

    /**
     * Returns aggregated donation statistics for a contact in a given currency.
     *
     * Issues two transport requests:
     * 1. Lifetime aggregates (SUM, COUNT, MIN, MAX).
     * 2. Last-12-months aggregates (SUM, COUNT with a date filter).
     *
     * When the contact has no contributions, all numeric fields are zero and
     * the date properties are `null`.
     *
     * Example:
     * ```php
     * $totals = $api->totalsForContact(42, 'PLN');
     * echo $totals->lifetimeTotal;
     * ```
     */
    public function totalsForContact(int $contactId, string $currency = 'PLN'): ContributionTotals
    {
        $baseWhere = [
            ['contact_id', '=', $contactId],
            ['currency', '=', $currency],
        ];

        $lifetimeResponse = $this->transport->send('Contribution', 'get', [
            'select' => ['SUM(total_amount)', 'COUNT(id)', 'MIN(receive_date)', 'MAX(receive_date)'],
            'where' => $baseWhere,
        ]);

        $twelveMonthsAgo = (new DateTimeImmutable())->modify('-12 months')->format('Y-m-d H:i:s');

        $recentResponse = $this->transport->send('Contribution', 'get', [
            'select' => ['SUM(total_amount)', 'COUNT(id)'],
            'where' => array_merge($baseWhere, [['receive_date', '>=', $twelveMonthsAgo]]),
        ]);

        $lifetimeRow = $lifetimeResponse->values[0] ?? [];
        $recentRow = $recentResponse->values[0] ?? [];

        $lifetimeTotal = is_array($lifetimeRow) && isset($lifetimeRow['SUM(total_amount)']) && (is_float($lifetimeRow['SUM(total_amount)']) || is_int($lifetimeRow['SUM(total_amount)'])) ? (float) $lifetimeRow['SUM(total_amount)'] : 0.0;
        $lifetimeCount = is_array($lifetimeRow) && isset($lifetimeRow['COUNT(id)']) && is_int($lifetimeRow['COUNT(id)']) ? $lifetimeRow['COUNT(id)'] : 0;
        $recentTotal = is_array($recentRow) && isset($recentRow['SUM(total_amount)']) && (is_float($recentRow['SUM(total_amount)']) || is_int($recentRow['SUM(total_amount)'])) ? (float) $recentRow['SUM(total_amount)'] : 0.0;
        $recentCount = is_array($recentRow) && isset($recentRow['COUNT(id)']) && is_int($recentRow['COUNT(id)']) ? $recentRow['COUNT(id)'] : 0;

        $firstAt = null;
        if (is_array($lifetimeRow) && isset($lifetimeRow['MIN(receive_date)']) && is_string($lifetimeRow['MIN(receive_date)'])) {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $lifetimeRow['MIN(receive_date)']);
            $firstAt = $parsed !== false ? $parsed : null;
        }

        $lastAt = null;
        if (is_array($lifetimeRow) && isset($lifetimeRow['MAX(receive_date)']) && is_string($lifetimeRow['MAX(receive_date)'])) {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $lifetimeRow['MAX(receive_date)']);
            $lastAt = $parsed !== false ? $parsed : null;
        }

        return new ContributionTotals(
            lifetimeTotal: $lifetimeTotal,
            lifetimeCount: $lifetimeCount,
            last12MonthsTotal: $recentTotal,
            last12MonthsCount: $recentCount,
            firstContributionAt: $firstAt,
            lastContributionAt: $lastAt,
            currency: $currency,
        );
    }

    /**
     * Returns completed contributions received on or after the given date.
     *
     * @return Result<Contribution>
     *
     * Example:
     * ```php
     * $recent = $api->completedSince(new DateTimeImmutable('-30 days'));
     * ```
     */
    public function completedSince(DateTimeImmutable $since): Result
    {
        $query = GetQuery::new()
            ->where('contribution_status_id:name', Operator::Equals, ContributionStatus::Completed->value)
            ->where('receive_date', Operator::GreaterOrEqual, $since->format('Y-m-d H:i:s'))
            ->orderBy('receive_date', 'DESC');

        return TypedResult::hydrate($this->executeGet($query), Contribution::class);
    }

    /**
     * Marks a contribution as Completed, optionally recording the transaction ID and received date.
     *
     * Example:
     * ```php
     * $api->markCompleted(99, 'TXN-001', new DateTimeImmutable());
     * ```
     */
    public function markCompleted(
        int $contributionId,
        ?string $trxnId = null,
        ?DateTimeImmutable $receivedDate = null,
    ): void {
        $values = ['contribution_status_id:name' => ContributionStatus::Completed->value];

        if ($trxnId !== null) {
            $values['trxn_id'] = $trxnId;
        }

        if ($receivedDate instanceof \DateTimeImmutable) {
            $values['receive_date'] = $receivedDate->format('Y-m-d H:i:s');
        }

        $this->executeAction(
            ActionRequest::update($this->entity, $values, [['id', '=', $contributionId]]),
        );
    }

    /**
     * Marks a contribution as Refunded.
     *
     * When `$reason` is provided, a Follow Up activity is created on the
     * contributor's contact record with the reason as the activity details.
     * This requires one additional transport call to retrieve the contact ID.
     *
     * Example:
     * ```php
     * $api->refund(99, 'Duplicate payment');
     * ```
     */
    public function refund(int $contributionId, ?string $reason = null): void
    {
        $this->transport->send('Contribution', 'update', [
            'where' => [['id', '=', $contributionId]],
            'values' => ['contribution_status_id:name' => ContributionStatus::Refunded->value],
        ]);

        if ($reason !== null) {
            $contrib = $this->transport->send('Contribution', 'get', [
                'where' => [['id', '=', $contributionId]],
                'select' => ['id', 'contact_id'],
                'limit' => 1,
            ]);

            $row = $contrib->values[0] ?? null;
            $contactId = is_array($row) && isset($row['contact_id']) && is_int($row['contact_id']) ? $row['contact_id'] : null;

            if ($contactId !== null) {
                $this->transport->send('Activity', 'create', [
                    'values' => [
                        'activity_type_id:name' => 'Follow Up',
                        'source_contact_id' => $contactId,
                        'target_contact_id' => [$contactId],
                        'subject' => 'Refund',
                        'details' => $reason,
                        'status_id:name' => 'Completed',
                    ],
                ]);
            }
        }
    }

    /**
     * Returns the field definitions for the Contribution entity.
     *
     * @return array<mixed>
     */
    #[\Override]
    public function getFields(): array
    {
        return parent::getFields();
    }

    /**
     * Returns the available actions for the Contribution entity.
     *
     * @return array<mixed>
     */
    #[\Override]
    public function getActions(): array
    {
        return parent::getActions();
    }
}
