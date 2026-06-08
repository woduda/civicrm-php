<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Entity;

use DateTimeImmutable;

/**
 * Typed representation of a CiviCRM Contribution record.
 *
 * `$status` is hydrated from `contribution_status_id:name` (string) when present
 * in the API response; otherwise from `contribution_status_id` (int) using the
 * default CiviCRM ID map. When neither key is available the status defaults to
 * {@see ContributionStatus::Pending}.
 */
final readonly class Contribution implements FromArrayInterface
{
    /**
     * @param array<string, mixed> $rawData Full original APIv4 row
     */
    public function __construct(
        public int $id,
        public int $contactId,
        public float $totalAmount,
        public string $currency,
        public DateTimeImmutable $receiveDate,
        public ContributionStatus $status,
        public int $financialTypeId,
        public ?string $source,
        public ?string $invoiceNumber,
        public ?string $trxnId,
        public ?int $paymentInstrumentId,
        public ?int $campaignId,
        public array $rawData,
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        $receiveDate = null;
        if (isset($row['receive_date']) && is_string($row['receive_date'])) {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $row['receive_date']);
            $receiveDate = $parsed !== false ? $parsed : null;
        }

        $status = self::hydrateStatus($row);

        return new self(
            id: isset($row['id']) && is_int($row['id']) ? $row['id'] : 0,
            contactId: isset($row['contact_id']) && is_int($row['contact_id']) ? $row['contact_id'] : 0,
            totalAmount: isset($row['total_amount']) && (is_float($row['total_amount']) || is_int($row['total_amount'])) ? (float) $row['total_amount'] : 0.0,
            currency: isset($row['currency']) && is_string($row['currency']) ? $row['currency'] : '',
            receiveDate: $receiveDate ?? new DateTimeImmutable('1970-01-01 00:00:00'),
            status: $status,
            financialTypeId: isset($row['financial_type_id']) && is_int($row['financial_type_id']) ? $row['financial_type_id'] : 0,
            source: isset($row['source']) && is_string($row['source']) ? $row['source'] : null,
            invoiceNumber: isset($row['invoice_number']) && is_string($row['invoice_number']) ? $row['invoice_number'] : null,
            trxnId: isset($row['trxn_id']) && is_string($row['trxn_id']) ? $row['trxn_id'] : null,
            paymentInstrumentId: isset($row['payment_instrument_id']) && is_int($row['payment_instrument_id']) ? $row['payment_instrument_id'] : null,
            campaignId: isset($row['campaign_id']) && is_int($row['campaign_id']) ? $row['campaign_id'] : null,
            rawData: $row,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function hydrateStatus(array $row): ContributionStatus
    {
        if (isset($row['contribution_status_id:name']) && is_string($row['contribution_status_id:name'])) {
            $status = ContributionStatus::tryFrom($row['contribution_status_id:name']);
            if ($status !== null) {
                return $status;
            }
        }

        if (isset($row['contribution_status_id']) && is_int($row['contribution_status_id'])) {
            try {
                return ContributionStatus::fromId($row['contribution_status_id']);
            } catch (\ValueError) {
                // Unknown custom installation ID — fall through to default.
            }
        }

        return ContributionStatus::Pending;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'contact_id' => $this->contactId,
            'total_amount' => $this->totalAmount,
            'currency' => $this->currency,
            'receive_date' => $this->receiveDate->format('Y-m-d H:i:s'),
            'contribution_status_id:name' => $this->status->value,
            'financial_type_id' => $this->financialTypeId,
        ];

        if ($this->source !== null) {
            $data['source'] = $this->source;
        }

        if ($this->invoiceNumber !== null) {
            $data['invoice_number'] = $this->invoiceNumber;
        }

        if ($this->trxnId !== null) {
            $data['trxn_id'] = $this->trxnId;
        }

        if ($this->paymentInstrumentId !== null) {
            $data['payment_instrument_id'] = $this->paymentInstrumentId;
        }

        if ($this->campaignId !== null) {
            $data['campaign_id'] = $this->campaignId;
        }

        return $data;
    }
}
