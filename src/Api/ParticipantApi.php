<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Api;

use DateTimeImmutable;
use Woduda\CiviCRM\Contract\TransportInterface;
use Woduda\CiviCRM\Entity\Participant;
use Woduda\CiviCRM\Entity\ParticipantStatus;
use Woduda\CiviCRM\Exception\ValidationException;
use Woduda\CiviCRM\Query\ActionRequest;
use Woduda\CiviCRM\Query\GetQuery;
use Woduda\CiviCRM\Query\Operator;
use Woduda\CiviCRM\Result\Result;
use Woduda\CiviCRM\Result\TypedResult;

/**
 * Typed API for the CiviCRM `Participant` entity.
 *
 * Example:
 * ```php
 * $participants = $client->participants();
 *
 * $p = $participants->register(42, 10);
 * $participants->markAttended($p->id);
 * $participants->cancel($p->id, 'No longer available');
 * ```
 */
final readonly class ParticipantApi extends AbstractEntityApi
{
    public function __construct(TransportInterface $transport)
    {
        parent::__construct($transport, 'Participant');
    }

    /**
     * Fetches participants matching an arbitrary query.
     *
     * @return Result<Participant>
     *
     * Example:
     * ```php
     * $result = $api->get(GetQuery::new()->where('event_id', Operator::Equals, 10));
     * ```
     */
    public function get(GetQuery $query): Result
    {
        return TypedResult::hydrate($this->executeGet($query), Participant::class);
    }

    /**
     * Registers a contact for an event.
     *
     * `$customFields` keys should use the `CustomField_group.field_name` notation
     * accepted by CiviCRM APIv4.
     *
     * @param array<string, mixed> $customFields Additional custom field values.
     * @throws ValidationException When the API returns no record.
     *
     * Example:
     * ```php
     * $p = $api->register(42, 10, ParticipantStatus::Registered, roleId: 1, source: 'Website');
     * ```
     */
    public function register(
        int $contactId,
        int $eventId,
        ParticipantStatus $status = ParticipantStatus::Registered,
        ?int $roleId = null,
        ?string $source = null,
        array $customFields = [],
    ): Participant {
        $values = array_merge([
            'contact_id' => $contactId,
            'event_id' => $eventId,
            'status_id:name' => $status->value,
        ], $customFields);

        if ($roleId !== null) {
            $values['role_id'] = $roleId;
        }

        if ($source !== null) {
            $values['source'] = $source;
        }

        $result = TypedResult::hydrate(
            $this->executeAction(ActionRequest::create($this->entity, $values)),
            Participant::class,
        );

        $first = $result->first();

        if (! $first instanceof Participant) {
            throw ValidationException::emptyApiResult($this->entity, 'create');
        }

        return $first;
    }

    /**
     * Marks a participant as Attended.
     *
     * Example:
     * ```php
     * $api->markAttended(1);
     * ```
     */
    public function markAttended(int $participantId): void
    {
        $this->executeAction(
            ActionRequest::update(
                $this->entity,
                ['status_id:name' => ParticipantStatus::Attended->value],
                [['id', '=', $participantId]],
            ),
        );
    }

    /**
     * Cancels a participant registration.
     *
     * When `$reason` is provided, a Follow Up activity is created on the contact's
     * record with the reason as the activity details. This requires one additional
     * transport call to retrieve the contact ID.
     *
     * @throws ValidationException When the reason activity cannot be created.
     *
     * Example:
     * ```php
     * $api->cancel(1, 'Travel conflict');
     * ```
     */
    public function cancel(int $participantId, ?string $reason = null): void
    {
        $this->transport->send('Participant', 'update', [
            'where' => [['id', '=', $participantId]],
            'values' => ['status_id:name' => ParticipantStatus::Cancelled->value],
        ]);

        if ($reason !== null) {
            $contactId = $this->fetchContactId($participantId);

            if ($contactId !== null) {
                $this->transport->send('Activity', 'create', [
                    'values' => [
                        'activity_type_id:name' => 'Follow Up',
                        'source_contact_id' => $contactId,
                        'target_contact_id' => [$contactId],
                        'subject' => 'Cancellation',
                        'details' => $reason,
                        'status_id:name' => 'Completed',
                    ],
                ]);
            }
        }
    }

    /**
     * Checks in a participant, setting their status to Attended.
     *
     * When `$at` is provided, a Check-in activity is created on the contact's record
     * with the check-in timestamp. This requires one additional transport call to
     * retrieve the contact ID.
     *
     * Example:
     * ```php
     * $api->checkIn(1, new DateTimeImmutable('2026-06-15 09:30:00'));
     * ```
     */
    public function checkIn(int $participantId, ?DateTimeImmutable $at = null): void
    {
        $this->transport->send('Participant', 'update', [
            'where' => [['id', '=', $participantId]],
            'values' => ['status_id:name' => ParticipantStatus::Attended->value],
        ]);

        if ($at instanceof \DateTimeImmutable) {
            $contactId = $this->fetchContactId($participantId);

            if ($contactId !== null) {
                $this->transport->send('Activity', 'create', [
                    'values' => [
                        'activity_type_id:name' => 'Check-in',
                        'source_contact_id' => $contactId,
                        'target_contact_id' => [$contactId],
                        'subject' => 'Event check-in',
                        'activity_date_time' => $at->format('Y-m-d H:i:s'),
                        'status_id:name' => 'Completed',
                    ],
                ]);
            }
        }
    }

    /**
     * Returns all participants for an event, optionally filtered by status.
     *
     * @return Result<Participant>
     *
     * Example:
     * ```php
     * $registered = $api->forEvent(10, ParticipantStatus::Registered);
     * ```
     */
    public function forEvent(int $eventId, ?ParticipantStatus $status = null): Result
    {
        $query = GetQuery::new()->where('event_id', Operator::Equals, $eventId);

        if ($status instanceof \Woduda\CiviCRM\Entity\ParticipantStatus) {
            $query = $query->where('status_id:name', Operator::Equals, $status->value);
        }

        return TypedResult::hydrate($this->executeGet($query), Participant::class);
    }

    /**
     * Returns all event registrations for a contact, most recent first.
     *
     * @return Result<Participant>
     *
     * Example:
     * ```php
     * $history = $api->forContact(42);
     * ```
     */
    public function forContact(int $contactId): Result
    {
        $query = GetQuery::new()
            ->where('contact_id', Operator::Equals, $contactId)
            ->orderBy('register_date', 'DESC');

        return TypedResult::hydrate($this->executeGet($query), Participant::class);
    }

    /**
     * Returns participant counts grouped by status for an event.
     *
     * Issues a single `Participant.get` request with `groupBy=[status_id]` and
     * `select=[status_id:name, COUNT(id)]`. Returns a map of status name → count,
     * suitable for dashboard reporting.
     *
     * @return array<string, int>
     *
     * Example:
     * ```php
     * $counts = $api->countByStatus(10);
     * // ['Registered' => 45, 'Attended' => 3, 'Cancelled' => 2]
     * ```
     */
    public function countByStatus(int $eventId): array
    {
        $response = $this->transport->send('Participant', 'get', [
            'select' => ['status_id:name', 'COUNT(id)'],
            'where' => [['event_id', '=', $eventId]],
            'groupBy' => ['status_id'],
        ]);

        $result = [];

        foreach ($response->values as $row) {
            if (
                is_array($row)
                && isset($row['status_id:name'])
                && is_string($row['status_id:name'])
                && isset($row['COUNT(id)'])
                && is_int($row['COUNT(id)'])
            ) {
                $result[$row['status_id:name']] = $row['COUNT(id)'];
            }
        }

        return $result;
    }

    /**
     * Returns the field definitions for the Participant entity.
     *
     * @return array<mixed>
     */
    #[\Override]
    public function getFields(): array
    {
        return parent::getFields();
    }

    /**
     * Returns the available actions for the Participant entity.
     *
     * @return array<mixed>
     */
    #[\Override]
    public function getActions(): array
    {
        return parent::getActions();
    }

    private function fetchContactId(int $participantId): ?int
    {
        $response = $this->transport->send('Participant', 'get', [
            'where' => [['id', '=', $participantId]],
            'select' => ['id', 'contact_id'],
            'limit' => 1,
        ]);

        $row = $response->values[0] ?? null;

        return is_array($row) && isset($row['contact_id']) && is_int($row['contact_id'])
            ? $row['contact_id']
            : null;
    }
}
