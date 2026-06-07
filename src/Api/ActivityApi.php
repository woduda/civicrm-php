<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Api;

use Woduda\CiviCRM\Contract\TransportInterface;
use Woduda\CiviCRM\Entity\Activity;
use Woduda\CiviCRM\Query\ActionRequest;
use Woduda\CiviCRM\Query\GetQuery;
use Woduda\CiviCRM\Query\Operator;
use Woduda\CiviCRM\Result\Result;
use Woduda\CiviCRM\Result\TypedResult;

/**
 * Typed API for the CiviCRM `Activity` entity.
 *
 * Example:
 * ```php
 * $activities = $client->activities();
 *
 * $activities->logForContact(42, 'Phone Call', ['subject' => 'Intake call']);
 *
 * $results = $activities->get($activities->forContact(42)->limit(10));
 * ```
 */
final readonly class ActivityApi extends AbstractEntityApi
{
    public function __construct(TransportInterface $transport)
    {
        parent::__construct($transport, 'Activity');
    }

    /**
     * Creates a new activity with the given field values.
     *
     * @param  array<string, mixed> $values
     * @return Result<Activity>
     *
     * Example:
     * ```php
     * $api->create(['activity_type_id.name' => 'Meeting', 'subject' => 'Kickoff']);
     * ```
     */
    public function create(array $values): Result
    {
        return TypedResult::hydrate(
            $this->executeAction(ActionRequest::create($this->entity, $values)),
            Activity::class,
        );
    }

    /**
     * Creates an activity linked to a contact as its source.
     *
     * `activity_type_id.name` accepts the human-readable label (e.g. `'Phone Call'`).
     * `status_id.name` defaults to `'Completed'`; pass `status_id.name` in `$extra`
     * to override. Any key in `$extra` wins over the default values.
     *
     * @param  array<string, mixed> $extra  Optional field overrides
     * @return Result<Activity>
     *
     * Example:
     * ```php
     * $api->logForContact(42, 'Phone Call', ['subject' => 'Intake call', 'duration' => 30]);
     * ```
     */
    public function logForContact(int $contactId, string $activityType, array $extra = []): Result
    {
        $values = array_merge([
            'activity_type_id.name' => $activityType,
            'source_contact_id' => $contactId,
            'status_id.name' => 'Completed',
        ], $extra);

        return $this->create($values);
    }

    /**
     * Returns a {@see GetQuery} pre-filtered for activities where `source_contact_id` equals
     * `$contactId`. Chain additional query methods as needed.
     *
     * Example:
     * ```php
     * $query = $api->forContact(42)->select('id', 'subject')->limit(20);
     * $results = $api->get($query);
     * ```
     */
    public function forContact(int $contactId): GetQuery
    {
        return GetQuery::new()->where('source_contact_id', Operator::Equals, $contactId);
    }

    /**
     * Fetches activities matching the query.
     *
     * @return Result<Activity>
     */
    public function get(GetQuery $query): Result
    {
        return TypedResult::hydrate($this->executeGet($query), Activity::class);
    }

    /**
     * Returns the field definitions for the Activity entity.
     *
     * @return array<mixed>
     */
    #[\Override]
    public function getFields(): array
    {
        return parent::getFields();
    }

    /**
     * Returns the available actions for the Activity entity.
     *
     * @return array<mixed>
     */
    #[\Override]
    public function getActions(): array
    {
        return parent::getActions();
    }
}
