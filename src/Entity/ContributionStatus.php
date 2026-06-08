<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Entity;

/**
 * CiviCRM `contribution_status` option group values.
 *
 * Backing values are the `name` field of the option_value records in CiviCRM's
 * default installation. These names are stable across upgrades but can differ
 * in customised installations — if your CiviCRM uses different names, hydrate
 * the raw string from the API response directly instead of relying on this enum.
 *
 * Default CiviCRM option value IDs (may differ per installation):
 *   1 = Completed, 2 = Pending, 3 = Cancelled, 4 = Failed, 5 = In Progress,
 *   6 = Overdue, 7 = Refunded, 8 = Partially paid, 9 = Chargeback
 */
enum ContributionStatus: string
{
    case Completed          = 'Completed';
    case Pending            = 'Pending';
    case Cancelled          = 'Cancelled';
    case Failed             = 'Failed';
    case InProgress         = 'In Progress';
    case Overdue            = 'Overdue';
    case Refunded           = 'Refunded';
    case PartiallyPaid      = 'Partially paid';
    case ChargebackReceived = 'Chargeback';

    /**
     * Resolves a default CiviCRM `contribution_status_id` integer to this enum.
     *
     * The mapping matches a fresh CiviCRM installation. If your site has
     * customised option values, prefer selecting `contribution_status_id:name`
     * in the API request and using {@see ContributionStatus::from()} instead.
     *
     * @throws \ValueError When the ID does not match any known default status.
     */
    public static function fromId(int $id): self
    {
        return match ($id) {
            1 => self::Completed,
            2 => self::Pending,
            3 => self::Cancelled,
            4 => self::Failed,
            5 => self::InProgress,
            6 => self::Overdue,
            7 => self::Refunded,
            8 => self::PartiallyPaid,
            9 => self::ChargebackReceived,
            default => throw new \ValueError(
                sprintf('"%d" is not a known default CiviCRM contribution_status_id.', $id),
            ),
        };
    }
}
