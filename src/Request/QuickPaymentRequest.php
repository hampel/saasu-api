<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Request;

use Hampel\Saasu\Api\Support\Cast;

/**
 * A payment applied to an invoice as it is inserted.
 *
 * INSERT ONLY: Saasu does not accept a quick payment on an update. And the amount "must be less
 * than the invoice total", in Saasu's words - a payment in full is a separate Payment.
 */
final class QuickPaymentRequest extends Payload
{
    protected const WRITABLE = [
        'DatePaid',
        'DateCleared',
        'BankedToAccountId',
        'Amount',
        'Reference',
        'Summary',
    ];

    public static function of(\DateTimeInterface|string $datePaid, int $bankAccountId, float $amount): self
    {
        return self::make()->withDatePaid($datePaid)->withBankedToAccountId(Cast::id($bankAccountId, 'bank account'))->withAmount($amount);
    }

    public function withDatePaid(\DateTimeInterface|string|null $datePaid): static
    {
        return $this->putDate('DatePaid', $datePaid);
    }

    public function withDateCleared(\DateTimeInterface|string|null $dateCleared): static
    {
        return $this->putDate('DateCleared', $dateCleared);
    }

    public function withBankedToAccountId(?int $bankedToAccountId): static
    {
        return $this->with('BankedToAccountId', $bankedToAccountId);
    }

    public function withAmount(int|float|null $amount): static
    {
        return $this->with('Amount', $amount);
    }

    public function withReference(?string $reference): static
    {
        return $this->with('Reference', $reference);
    }

    public function withSummary(?string $summary): static
    {
        return $this->with('Summary', $summary);
    }
}
