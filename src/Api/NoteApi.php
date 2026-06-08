<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Api;

use Woduda\CiviCRM\Contract\TransportInterface;
use Woduda\CiviCRM\Entity\Note;
use Woduda\CiviCRM\Exception\ValidationException;
use Woduda\CiviCRM\Query\ActionRequest;
use Woduda\CiviCRM\Query\GetQuery;
use Woduda\CiviCRM\Query\Operator;
use Woduda\CiviCRM\Result\Result;
use Woduda\CiviCRM\Result\TypedResult;

/**
 * Typed API for the CiviCRM `Note` entity.
 *
 * Example:
 * ```php
 * $notes = $client->notes();
 *
 * $note = $notes->addToContact(42, 'Called to confirm appointment.', 'Follow-up');
 * $all  = $notes->forContact(42);
 * $notes->delete($note->id);
 * ```
 */
final readonly class NoteApi extends AbstractEntityApi
{
    public function __construct(TransportInterface $transport)
    {
        parent::__construct($transport, 'Note');
    }

    /**
     * Creates a note attached to a contact.
     *
     * `$privacy` controls visibility; the CiviCRM default is `'public'`.
     *
     * @throws ValidationException When the API returns an empty result.
     *
     * Example:
     * ```php
     * $note = $api->addToContact(42, 'Called to confirm appointment.', 'Follow-up');
     * ```
     */
    public function addToContact(
        int $contactId,
        string $note,
        ?string $subject = null,
        string $privacy = 'public',
    ): Note {
        $values = [
            'entity_table' => 'civicrm_contact',
            'entity_id' => $contactId,
            'note' => $note,
            'privacy' => $privacy,
        ];

        if ($subject !== null) {
            $values['subject'] = $subject;
        }

        $result = TypedResult::hydrate(
            $this->executeAction(ActionRequest::create($this->entity, $values)),
            Note::class,
        );

        $first = $result->first();

        if (! $first instanceof Note) {
            throw ValidationException::emptyApiResult($this->entity, 'create');
        }

        return $first;
    }

    /**
     * Returns all notes for a contact, most recently modified first.
     *
     * @return Result<Note>
     *
     * Example:
     * ```php
     * $notes = $api->forContact(42);
     * foreach ($notes->values as $note) {
     *     echo $note->note;
     * }
     * ```
     */
    public function forContact(int $contactId): Result
    {
        $query = GetQuery::new()
            ->where('entity_table', Operator::Equals, 'civicrm_contact')
            ->where('entity_id', Operator::Equals, $contactId)
            ->orderBy('modified_date', 'DESC');

        return TypedResult::hydrate($this->executeGet($query), Note::class);
    }

    /**
     * Deletes a note by its ID.
     *
     * Example:
     * ```php
     * $api->delete(17);
     * ```
     */
    public function delete(int $noteId): void
    {
        $this->executeAction(
            ActionRequest::delete($this->entity, [['id', '=', $noteId]]),
        );
    }

    /**
     * Fetches notes matching an arbitrary query.
     *
     * @return Result<Note>
     *
     * Example:
     * ```php
     * $notes = $api->get(GetQuery::new()->where('subject', Operator::Equals, 'Intake')->limit(10));
     * ```
     */
    public function get(GetQuery $query): Result
    {
        return TypedResult::hydrate($this->executeGet($query), Note::class);
    }

    /**
     * Returns the field definitions for the Note entity.
     *
     * @return array<mixed>
     */
    #[\Override]
    public function getFields(): array
    {
        return parent::getFields();
    }

    /**
     * Returns the available actions for the Note entity.
     *
     * @return array<mixed>
     */
    #[\Override]
    public function getActions(): array
    {
        return parent::getActions();
    }
}
