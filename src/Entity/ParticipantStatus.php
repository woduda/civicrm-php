<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Entity;

/**
 * CiviCRM `participant_status` option group values.
 *
 * Backing values are the `name` field of the option_value records in CiviCRM's
 * default installation. CiviCRM assigns each status a **class** that determines
 * how participants are counted:
 *
 * - **Positive** (Registered, Attended) — participant is counted against the event's
 *   `max_participants` cap. Use {@see isPositive()} to test for this class.
 * - **Pending** (Pending from pay later, On waitlist, Awaiting approval) — participant
 *   has expressed intent but is not yet confirmed. Use {@see isPending()}.
 * - **Negative** (No-show, Cancelled, Rejected, Expired) — participant did not attend
 *   or was removed. Use {@see isNegative()}.
 *
 * Default CiviCRM option value IDs (may differ per installation):
 *   1 = Registered, 2 = Attended, 3 = No-show, 4 = Cancelled,
 *   5 = Pending from pay later, 6 = On waitlist, 7 = Awaiting approval,
 *   8 = Rejected, 9 = Expired
 */
enum ParticipantStatus: string
{
    case Registered       = 'Registered';
    case Attended         = 'Attended';
    case NoShow           = 'No-show';
    case Cancelled        = 'Cancelled';
    case PendingPayLater  = 'Pending from pay later';
    case OnWaitlist       = 'On waitlist';
    case AwaitingApproval = 'Awaiting approval';
    case Rejected         = 'Rejected';
    case Expired          = 'Expired';

    /**
     * Resolves a default CiviCRM `participant_status_id` integer to this enum.
     *
     * The mapping matches a fresh CiviCRM installation. If your site has customised
     * option values, prefer selecting `status_id:name` in the API request and using
     * {@see ParticipantStatus::from()} instead.
     *
     * @throws \ValueError When the ID does not match any known default status.
     */
    public static function fromId(int $id): self
    {
        return match ($id) {
            1 => self::Registered,
            2 => self::Attended,
            3 => self::NoShow,
            4 => self::Cancelled,
            5 => self::PendingPayLater,
            6 => self::OnWaitlist,
            7 => self::AwaitingApproval,
            8 => self::Rejected,
            9 => self::Expired,
            default => throw new \ValueError(
                sprintf('"%d" is not a known default CiviCRM participant_status_id.', $id),
            ),
        };
    }

    /**
     * Returns true for statuses in the **Positive** class (Registered, Attended).
     *
     * Positive participants are counted against the event's `max_participants` cap.
     */
    public function isPositive(): bool
    {
        return match ($this) {
            self::Registered, self::Attended => true,
            default => false,
        };
    }

    /**
     * Returns true for statuses in the **Pending** class
     * (Pending from pay later, On waitlist, Awaiting approval).
     */
    public function isPending(): bool
    {
        return match ($this) {
            self::PendingPayLater, self::OnWaitlist, self::AwaitingApproval => true,
            default => false,
        };
    }

    /**
     * Returns true for statuses in the **Negative** class
     * (No-show, Cancelled, Rejected, Expired).
     */
    public function isNegative(): bool
    {
        return match ($this) {
            self::NoShow, self::Cancelled, self::Rejected, self::Expired => true,
            default => false,
        };
    }
}
