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
     * Put the payment in a currency, with its exchange rate.
     *
     * USE THIS RATHER THAN withCurrency() ALONE, for the reason InvoiceRequest::inCurrency()
     * gives: a currency without the automatic rate keeps a rate of 1.0. Without a rate this asks
     * Saasu to fill one in; with one it sends that rate and turns the automatic rate off.
     *
     * Measured 2026-09-14: a USD payment was accepted into a USD bank account and into an AUD one,
     * both at the rate Saasu filled in.
     */
    public function inCurrency(string $currency, int|float|null $fxRate = null): self
    {
        $currency = strtoupper(trim($currency));

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException(sprintf('A currency is a three-letter ISO 4217 code; "%s" is not.', $currency));
        }

        if ($fxRate !== null && $fxRate <= 0) {
            throw new InvalidArgumentException('An exchange rate must be positive.');
        }

        $request = $this->withCurrency($currency)->withAutoPopulateFxRate($fxRate === null);

        return $fxRate === null ? $request->without('FxRate') : $request->withFxRate($fxRate);
    }

    /**
     * Put the payment in a currency at the rate that makes it cost exactly what the bank charged.
     *
     *     PaymentRequest::paid($date, $audBankAccountId, [PaymentItemRequest::of($invoiceId, 49.99)])
     *         ->inCurrencyForBaseAmount('USD', 49.99, 72.37)
     *
     * For a foreign-currency purchase paid from a base-currency account, where the statement says
     * what was really charged. Saasu takes a payment's rate, not its base amount, so the rate sent is
     * the base amount divided by the foreign one.
     *
     * MEASURED EXACT TO THE CENT, 2026-09-14, for USD 49.99 / AUD 72.37, USD 1234.56 / AUD 1901.07,
     * USD 0.99 / AUD 1.53 and USD 333.33 / AUD 499.99: the bank account moved by the statement
     * figure each time, and the invoice was paid in full. Saasu keeps the rate to ten decimal
     * places, which holds the base amount to the cent for any foreign amount below 50 million.
     */
    public function inCurrencyForBaseAmount(string $currency, float $foreignAmount, float $baseAmount): self
    {
        if ($foreignAmount <= 0 || $baseAmount <= 0) {
            throw new InvalidArgumentException('Both amounts must be positive to work out an exchange rate.');
        }

        return $this->inCurrency($currency, round($baseAmount / $foreignAmount, 10));
    }

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

    /**
     * A merchant fee taken out of the payment before it reached the bank - a card or PayPal
     * settlement.
     *
     * THE INVOICE STAYS PAID IN FULL; THE BANK RECEIVES LESS BY THE FEE. Measured 2026-09-14: a
     * payment of 110.00 with a fee of 3.30 settled a 110.00 invoice and put 106.70 in the account,
     * and the same fee added later to an existing payment - built from() it - moved the balance by
     * -3.30 and left the invoice paid. On a foreign-currency payment the fee is in that currency and
     * converted at the payment's rate.
     *
     * THE BANK ACCOUNT NEEDS A MERCHANT FEE ACCOUNT, set with
     * AccountRequest::withMerchantFeeAccountId(). Without one Saasu refuses with a 400 that says
     * only "Unable to perform the request."
     */
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
