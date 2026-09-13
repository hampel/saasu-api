<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Request;

use Hampel\Saasu\Api\Enum\PaymentType;
use Hampel\Saasu\Api\Exception\InvalidArgumentException;
use Hampel\Saasu\Api\Support\Cast;

/**
 * A payment to insert, or the changes to one.
 *
 *     PaymentRequest::received('2026-09-13', bankAccountId: 1858, [PaymentItemRequest::of(5093684, 110.00)])
 *
 * `FeeAmount` is accepted only when the bank account has a merchant fee account set. An omitted
 * `Currency` becomes the file's base currency.
 */
final class PaymentRequest extends Payload
{
    protected const WRITABLE = [
        'TransactionType',
        'TransactionDate',
        'PaymentAccountId',
        'PaymentItems',
        'TotalAmount',
        'FeeAmount',
        'Summary',
        'Reference',
        'Notes',
        'ClearedDate',
        'Currency',
        'AutoPopulateFxRate',
        'FxRate',
        'RequiresFollowUp',
        'LastUpdatedId',
    ];

    /**
     * Money received against sales.
     *
     * @param  list<PaymentItemRequest>  $invoices
     */
    public static function received(\DateTimeInterface|string $date, int $bankAccountId, array $invoices): self
    {
        return self::build(PaymentType::SalePayment, $date, $bankAccountId, $invoices);
    }

    /**
     * Money paid against purchases.
     *
     * @param  list<PaymentItemRequest>  $invoices
     */
    public static function paid(\DateTimeInterface|string $date, int $bankAccountId, array $invoices): self
    {
        return self::build(PaymentType::PurchasePayment, $date, $bankAccountId, $invoices);
    }

    /**
     * @param  list<PaymentItemRequest>  $invoices
     */
    private static function build(PaymentType $type, \DateTimeInterface|string $date, int $bankAccountId, array $invoices): self
    {
        if ($invoices === []) {
            throw new InvalidArgumentException('A payment needs at least one invoice it pays.');
        }

        return self::make()
            ->withTransactionType($type)
            ->withTransactionDate($date)
            ->withPaymentAccountId(Cast::id($bankAccountId, 'bank account'))
            ->withPaymentItems($invoices);
    }

    public function withTransactionType(PaymentType|string|null $transactionType): static
    {
        return $this->with('TransactionType', $transactionType instanceof \BackedEnum ? $transactionType->value : $transactionType);
    }

    public function withTransactionDate(\DateTimeInterface|string|null $transactionDate): static
    {
        return $this->putDate('TransactionDate', $transactionDate);
    }

    public function withPaymentAccountId(?int $paymentAccountId): static
    {
        return $this->with('PaymentAccountId', $paymentAccountId);
    }

    /**
     * @param  list<PaymentItemRequest|array<string, mixed>>  $paymentItems
     */
    public function withPaymentItems(array $paymentItems): static
    {
        return $this->putList('PaymentItems', $paymentItems);
    }

    public function withTotalAmount(int|float|null $totalAmount): static
    {
        return $this->with('TotalAmount', $totalAmount);
    }

    public function withFeeAmount(int|float|null $feeAmount): static
    {
        return $this->with('FeeAmount', $feeAmount);
    }

    public function withSummary(?string $summary): static
    {
        return $this->with('Summary', $summary);
    }

    public function withReference(?string $reference): static
    {
        return $this->with('Reference', $reference);
    }

    public function withNotes(?string $notes): static
    {
        return $this->with('Notes', $notes);
    }

    public function withClearedDate(\DateTimeInterface|string|null $clearedDate): static
    {
        return $this->putDate('ClearedDate', $clearedDate);
    }

    public function withCurrency(?string $currency): static
    {
        return $this->with('Currency', $currency);
    }

    public function withAutoPopulateFxRate(?bool $autoPopulateFxRate): static
    {
        return $this->with('AutoPopulateFxRate', $autoPopulateFxRate);
    }

    public function withFxRate(int|float|null $fxRate): static
    {
        return $this->with('FxRate', $fxRate);
    }

    public function withRequiresFollowUp(?bool $requiresFollowUp): static
    {
        return $this->with('RequiresFollowUp', $requiresFollowUp);
    }

    public function withLastUpdatedId(?string $lastUpdatedId): static
    {
        return $this->with('LastUpdatedId', $lastUpdatedId);
    }
}
