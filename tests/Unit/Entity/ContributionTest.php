<?php

declare(strict_types=1);

use Woduda\CiviCRM\Entity\Contribution;
use Woduda\CiviCRM\Entity\ContributionStatus;

// --- ContributionStatus::fromId ---

it('fromId returns Completed for id 1', function (): void {
    expect(ContributionStatus::fromId(1))->toBe(ContributionStatus::Completed);
});

it('fromId returns Pending for id 2', function (): void {
    expect(ContributionStatus::fromId(2))->toBe(ContributionStatus::Pending);
});

it('fromId returns Cancelled for id 3', function (): void {
    expect(ContributionStatus::fromId(3))->toBe(ContributionStatus::Cancelled);
});

it('fromId returns Failed for id 4', function (): void {
    expect(ContributionStatus::fromId(4))->toBe(ContributionStatus::Failed);
});

it('fromId returns InProgress for id 5', function (): void {
    expect(ContributionStatus::fromId(5))->toBe(ContributionStatus::InProgress);
});

it('fromId returns Overdue for id 6', function (): void {
    expect(ContributionStatus::fromId(6))->toBe(ContributionStatus::Overdue);
});

it('fromId returns Refunded for id 7', function (): void {
    expect(ContributionStatus::fromId(7))->toBe(ContributionStatus::Refunded);
});

it('fromId returns PartiallyPaid for id 8', function (): void {
    expect(ContributionStatus::fromId(8))->toBe(ContributionStatus::PartiallyPaid);
});

it('fromId returns ChargebackReceived for id 9', function (): void {
    expect(ContributionStatus::fromId(9))->toBe(ContributionStatus::ChargebackReceived);
});

it('fromId throws ValueError for unknown id', function (): void {
    expect(fn() => ContributionStatus::fromId(99))->toThrow(\ValueError::class);
});

// --- Contribution::fromArray — status hydration ---

it('fromArray hydrates status from contribution_status_id:name string', function (): void {
    $row = fixtureFirstRow('contribution-single.json');
    $contribution = Contribution::fromArray($row);

    expect($contribution->status)->toBe(ContributionStatus::Completed);
});

it('fromArray hydrates status from integer contribution_status_id when name key absent', function (): void {
    $row = [
        'id' => 5,
        'contact_id' => 1,
        'total_amount' => 100.0,
        'currency' => 'PLN',
        'receive_date' => '2026-01-01 00:00:00',
        'contribution_status_id' => 7,
        'financial_type_id' => 1,
    ];

    $contribution = Contribution::fromArray($row);

    expect($contribution->status)->toBe(ContributionStatus::Refunded);
});

it('fromArray falls back to Pending when status_id is unknown custom int', function (): void {
    $row = [
        'id' => 5,
        'contact_id' => 1,
        'total_amount' => 100.0,
        'currency' => 'PLN',
        'receive_date' => '2026-01-01 00:00:00',
        'contribution_status_id' => 999,
        'financial_type_id' => 1,
    ];

    $contribution = Contribution::fromArray($row);

    expect($contribution->status)->toBe(ContributionStatus::Pending);
});

it('fromArray falls back to Pending when no status key is present', function (): void {
    $row = [
        'id' => 5,
        'contact_id' => 1,
        'total_amount' => 100.0,
        'currency' => 'PLN',
        'receive_date' => '2026-01-01 00:00:00',
        'financial_type_id' => 1,
    ];

    $contribution = Contribution::fromArray($row);

    expect($contribution->status)->toBe(ContributionStatus::Pending);
});

// --- Contribution::fromArray — field mapping ---

it('fromArray maps all scalar fields from the fixture row', function (): void {
    $row = fixtureFirstRow('contribution-single.json');
    $contribution = Contribution::fromArray($row);

    expect($contribution->id)->toBe(99)
        ->and($contribution->contactId)->toBe(42)
        ->and($contribution->totalAmount)->toBe(500.0)
        ->and($contribution->currency)->toBe('PLN')
        ->and($contribution->financialTypeId)->toBe(1)
        ->and($contribution->source)->toBe('Online donation')
        ->and($contribution->invoiceNumber)->toBe('INV-2026-001')
        ->and($contribution->trxnId)->toBe('TXN-ABC123')
        ->and($contribution->paymentInstrumentId)->toBe(1)
        ->and($contribution->campaignId)->toBeNull();
});

it('fromArray parses receive_date to DateTimeImmutable', function (): void {
    $row = fixtureFirstRow('contribution-single.json');
    $contribution = Contribution::fromArray($row);

    expect($contribution->receiveDate)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($contribution->receiveDate->format('Y-m-d'))->toBe('2026-06-01');
});

it('fromArray stores full row in rawData', function (): void {
    $row = fixtureFirstRow('contribution-single.json');
    $contribution = Contribution::fromArray($row);

    expect($contribution->rawData)->toBe($row);
});

// --- Contribution::toArray ---

it('toArray round-trips all non-null fields', function (): void {
    $row = fixtureFirstRow('contribution-single.json');
    $contribution = Contribution::fromArray($row);
    $data = $contribution->toArray();

    expect($data['id'])->toBe(99)
        ->and($data['contact_id'])->toBe(42)
        ->and($data['total_amount'])->toBe(500.0)
        ->and($data['currency'])->toBe('PLN')
        ->and($data['receive_date'])->toBe('2026-06-01 12:00:00')
        ->and($data['contribution_status_id:name'])->toBe('Completed')
        ->and($data['financial_type_id'])->toBe(1)
        ->and($data['source'])->toBe('Online donation')
        ->and($data['invoice_number'])->toBe('INV-2026-001')
        ->and($data['trxn_id'])->toBe('TXN-ABC123')
        ->and($data['payment_instrument_id'])->toBe(1);
});

it('toArray omits null optional fields', function (): void {
    $row = [
        'id' => 1,
        'contact_id' => 10,
        'total_amount' => 50.0,
        'currency' => 'EUR',
        'receive_date' => '2026-03-01 00:00:00',
        'contribution_status_id:name' => 'Pending',
        'financial_type_id' => 2,
    ];

    $contribution = Contribution::fromArray($row);
    $data = $contribution->toArray();

    expect($data)->not->toHaveKey('source')
        ->and($data)->not->toHaveKey('invoice_number')
        ->and($data)->not->toHaveKey('trxn_id')
        ->and($data)->not->toHaveKey('payment_instrument_id')
        ->and($data)->not->toHaveKey('campaign_id');
});
