<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Api;

use DateTimeImmutable;
use Woduda\CiviCRM\Contract\ClockInterface;
use Woduda\CiviCRM\Contract\TransportInterface;
use Woduda\CiviCRM\Entity\Event;
use Woduda\CiviCRM\Entity\ParticipantStatus;
use Woduda\CiviCRM\Query\GetQuery;
use Woduda\CiviCRM\Query\Operator;
use Woduda\CiviCRM\Result\Result;
use Woduda\CiviCRM\Result\TypedResult;

/**
 * Typed API for the CiviCRM `Event` entity.
 *
 * Example:
 * ```php
 * $events = $client->events();
 *
 * $upcoming = $events->upcoming(5);
 * $event    = $events->findByTitle('Annual Gala');
 * $isFull   = $events->isFull(42);
 * ```
 */
final readonly class EventApi extends AbstractEntityApi
{
    public function __construct(
        TransportInterface $transport,
        private ClockInterface $clock,
    ) {
        parent::__construct($transport, 'Event');
    }

    /**
     * Fetches events matching an arbitrary query.
     *
     * @return Result<Event>
     *
     * Example:
     * ```php
     * $result = $api->get(GetQuery::new()->where('event_type_id', Operator::Equals, 1));
     * ```
     */
    public function get(GetQuery $query): Result
    {
        return TypedResult::hydrate($this->executeGet($query), Event::class);
    }

    /**
     * Returns a single event by ID, or `null` when not found.
     *
     * Example:
     * ```php
     * $event = $api->getById(10);
     * ```
     */
    public function getById(int $id): ?Event
    {
        $query = GetQuery::new()
            ->where('id', Operator::Equals, $id)
            ->limit(1);

        return TypedResult::hydrate($this->executeGet($query), Event::class)->first();
    }

    /**
     * Returns the first event whose title exactly matches `$title`, or `null`.
     *
     * Example:
     * ```php
     * $event = $api->findByTitle('Annual Gala');
     * ```
     */
    public function findByTitle(string $title): ?Event
    {
        $query = GetQuery::new()
            ->where('title', Operator::Equals, $title)
            ->limit(1);

        return TypedResult::hydrate($this->executeGet($query), Event::class)->first();
    }

    /**
     * Returns upcoming active events ordered by start date ascending.
     *
     * "Upcoming" means `start_date` is strictly after the current clock time and
     * `is_active` is `true`. The clock is injected at construction time, which
     * allows tests to fix the reference instant deterministically.
     *
     * @return Result<Event>
     *
     * Example:
     * ```php
     * $next5 = $api->upcoming(5);
     * ```
     */
    public function upcoming(int $limit = 10): Result
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');

        $query = GetQuery::new()
            ->where('start_date', Operator::GreaterThan, $now)
            ->where('is_active', Operator::Equals, true)
            ->orderBy('start_date', 'ASC')
            ->limit($limit);

        return TypedResult::hydrate($this->executeGet($query), Event::class);
    }

    /**
     * Returns events whose `start_date` falls within the given range (inclusive).
     *
     * @return Result<Event>
     *
     * Example:
     * ```php
     * $q1 = $api->between(new DateTimeImmutable('2026-01-01'), new DateTimeImmutable('2026-03-31'));
     * ```
     */
    public function between(DateTimeImmutable $from, DateTimeImmutable $to): Result
    {
        $query = GetQuery::new()
            ->where('start_date', Operator::GreaterOrEqual, $from->format('Y-m-d H:i:s'))
            ->where('start_date', Operator::LessOrEqual, $to->format('Y-m-d H:i:s'))
            ->orderBy('start_date', 'ASC');

        return TypedResult::hydrate($this->executeGet($query), Event::class);
    }

    /**
     * Returns the number of participants for an event, optionally filtered by status.
     *
     * Issues a single `Participant.get` request with `select=['row_count']` to avoid
     * fetching full participant records.
     *
     * Example:
     * ```php
     * $total      = $api->participantCount(10);
     * $registered = $api->participantCount(10, ParticipantStatus::Registered);
     * ```
     */
    public function participantCount(int $eventId, ?ParticipantStatus $status = null): int
    {
        $where = [['event_id', '=', $eventId]];

        if ($status instanceof \Woduda\CiviCRM\Entity\ParticipantStatus) {
            $where[] = ['status_id:name', '=', $status->value];
        }

        $response = $this->transport->send('Participant', 'get', [
            'select' => ['row_count'],
            'where' => $where,
        ]);

        $row = $response->values[0] ?? null;

        return is_array($row) && isset($row['row_count']) && is_int($row['row_count'])
            ? $row['row_count']
            : 0;
    }

    /**
     * Returns `true` when the event has reached its participant capacity.
     *
     * Capacity is determined by counting participants whose status is **Positive**
     * (Registered or Attended) and comparing the count against `max_participants`.
     * When `max_participants` is `null` the event has no cap and this method always
     * returns `false`.
     *
     * Issues at most two transport requests: one to fetch the event, one to count
     * confirmed participants.
     *
     * Example:
     * ```php
     * if ($api->isFull(10)) { echo 'Event is fully booked.'; }
     * ```
     */
    public function isFull(int $eventId): bool
    {
        $event = $this->getById($eventId);

        if (!$event instanceof \Woduda\CiviCRM\Entity\Event || $event->maxParticipants === null) {
            return false;
        }

        $response = $this->transport->send('Participant', 'get', [
            'select' => ['row_count'],
            'where' => [
                ['event_id', '=', $eventId],
                ['status_id:name', 'IN', ['Registered', 'Attended']],
            ],
        ]);

        $row = $response->values[0] ?? null;
        $count = is_array($row) && isset($row['row_count']) && is_int($row['row_count'])
            ? $row['row_count']
            : 0;

        return $count >= $event->maxParticipants;
    }

    /**
     * Returns the field definitions for the Event entity.
     *
     * @return array<mixed>
     */
    #[\Override]
    public function getFields(): array
    {
        return parent::getFields();
    }

    /**
     * Returns the available actions for the Event entity.
     *
     * @return array<mixed>
     */
    #[\Override]
    public function getActions(): array
    {
        return parent::getActions();
    }
}
