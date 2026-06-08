<?php

declare(strict_types=1);

use Woduda\CiviCRM\Entity\ParticipantStatus;

// ---------------------------------------------------------------------------
// fromId
// ---------------------------------------------------------------------------

it('fromId resolves all default CiviCRM participant status IDs', function (int $id, ParticipantStatus $expected): void {
    expect(ParticipantStatus::fromId($id))->toBe($expected);
})->with([
    [1, ParticipantStatus::Registered],
    [2, ParticipantStatus::Attended],
    [3, ParticipantStatus::NoShow],
    [4, ParticipantStatus::Cancelled],
    [5, ParticipantStatus::PendingPayLater],
    [6, ParticipantStatus::OnWaitlist],
    [7, ParticipantStatus::AwaitingApproval],
    [8, ParticipantStatus::Rejected],
    [9, ParticipantStatus::Expired],
]);

it('fromId throws ValueError for unknown ID', function (): void {
    expect(fn() => ParticipantStatus::fromId(99))->toThrow(\ValueError::class);
});

// ---------------------------------------------------------------------------
// isPositive
// ---------------------------------------------------------------------------

it('isPositive returns true only for Positive-class statuses', function (ParticipantStatus $status, bool $expected): void {
    expect($status->isPositive())->toBe($expected);
})->with([
    [ParticipantStatus::Registered,       true],
    [ParticipantStatus::Attended,         true],
    [ParticipantStatus::NoShow,           false],
    [ParticipantStatus::Cancelled,        false],
    [ParticipantStatus::PendingPayLater,  false],
    [ParticipantStatus::OnWaitlist,       false],
    [ParticipantStatus::AwaitingApproval, false],
    [ParticipantStatus::Rejected,         false],
    [ParticipantStatus::Expired,          false],
]);

// ---------------------------------------------------------------------------
// isPending
// ---------------------------------------------------------------------------

it('isPending returns true only for Pending-class statuses', function (ParticipantStatus $status, bool $expected): void {
    expect($status->isPending())->toBe($expected);
})->with([
    [ParticipantStatus::Registered,       false],
    [ParticipantStatus::Attended,         false],
    [ParticipantStatus::NoShow,           false],
    [ParticipantStatus::Cancelled,        false],
    [ParticipantStatus::PendingPayLater,  true],
    [ParticipantStatus::OnWaitlist,       true],
    [ParticipantStatus::AwaitingApproval, true],
    [ParticipantStatus::Rejected,         false],
    [ParticipantStatus::Expired,          false],
]);

// ---------------------------------------------------------------------------
// isNegative
// ---------------------------------------------------------------------------

it('isNegative returns true only for Negative-class statuses', function (ParticipantStatus $status, bool $expected): void {
    expect($status->isNegative())->toBe($expected);
})->with([
    [ParticipantStatus::Registered,       false],
    [ParticipantStatus::Attended,         false],
    [ParticipantStatus::NoShow,           true],
    [ParticipantStatus::Cancelled,        true],
    [ParticipantStatus::PendingPayLater,  false],
    [ParticipantStatus::OnWaitlist,       false],
    [ParticipantStatus::AwaitingApproval, false],
    [ParticipantStatus::Rejected,         true],
    [ParticipantStatus::Expired,          true],
]);

// ---------------------------------------------------------------------------
// classification completeness: every case belongs to exactly one class
// ---------------------------------------------------------------------------

it('every ParticipantStatus belongs to exactly one classification class', function (ParticipantStatus $status): void {
    $classes = [
        $status->isPositive(),
        $status->isPending(),
        $status->isNegative(),
    ];

    expect(array_filter($classes))->toHaveCount(1);
})->with(array_map(fn($s) => [$s], ParticipantStatus::cases()));
