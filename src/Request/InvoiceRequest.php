<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Request;

use Hampel\Saasu\Api\Enum\InvoiceLayout;
use Hampel\Saasu\Api\Enum\InvoiceType;
use Hampel\Saasu\Api\Enum\TransactionType;
use Hampel\Saasu\Api\Exception\InvalidArgumentException;

/**
 * A sale or purchase to insert, or the changes to one.
 *
 *     InvoiceRequest::sale(InvoiceType::TaxInvoice, InvoiceLayout::Service, '2026-09-13')
 *         ->withBillingContactId(54353)
 *         ->withAutoNumber()
 *         ->withLineItems([InvoiceLineItemRequest::service('Consulting', 1234, 1650.00, 'G1')])
 *
 * SAASU REQUIRES FIVE THINGS on an insert - transaction type, invoice type, layout, date and at
 * least one line - and sale() and purchase() take the first four so they cannot be forgotten.
 *
 * `IsTaxInc` IS ASSUMED FALSE WHEN IT IS NOT SENT, per Saasu's documentation - so a line amount of
 * 110.00 meant to include GST becomes 110.00 plus GST unless withTaxInclusive(true) is set.
 *
 * TWO THINGS HERE ACT RATHER THAN DESCRIBE, and neither is copied by from(): emailing the invoice
 * (withEmailToContact()) and applying a payment (withQuickPayment(), insert only). An update built
 * from a record therefore never re-sends an email or re-applies a payment by accident.
 */
final class InvoiceRequest extends Payload
{
    protected const WRITABLE = [
        'TransactionType',
        'InvoiceType',
        'Layout',
        'TransactionDate',
        'DueDate',
        'InvoiceNumber',
        'PurchaseOrderNumber',
        'BillingContactId',
        'ShippingContactId',
        'Summary',
        'NotesInternal',
        'NotesExternal',
        'IsTaxInc',
        'Currency',
        'FxRate',
        'AutoPopulateFxRate',
        'RequiresFollowUp',
        'BrandId',
        'TemplateId',
        'ForEntityTypeId',
        'Terms',
        'LineItems',
        'Tags',
        'LastUpdatedId',
    ];

    /**
     * Saasu's token for "generate the next invoice number". Documented on `InvoiceNumber`.
     */
    public const AUTO_NUMBER = '<Auto Number>';

    public static function sale(InvoiceType $type, InvoiceLayout $layout, \DateTimeInterface|string $date): self
    {
        return self::make()->withTransactionType(TransactionType::Sale)->withInvoiceType($type)->withLayout($layout)->withTransactionDate($date);
    }

    public static function purchase(InvoiceType $type, InvoiceLayout $layout, \DateTimeInterface|string $date): self
    {
        return self::make()->withTransactionType(TransactionType::Purchase)->withInvoiceType($type)->withLayout($layout)->withTransactionDate($date);
    }

    /**
     * Put the invoice in a currency, with its exchange rate.
     *
     * USE THIS RATHER THAN withCurrency() ALONE. Measured 2026-09-14: an invoice changed to USD
     * with nothing else changed kept an `FxRate` of 1.0 - so USD 110 would be booked as AUD 110,
     * without a word from Saasu. Without a rate this asks Saasu to fill one in, which it does from
     * the invoice's own date (measured: 1.395 for today, 1.420 for 90 days earlier); with one it
     * sends that rate and turns the automatic rate off.
     *
     * For an invoice already in the base currency, build the request from() it and call this:
     * the line amounts are not converted - 110 AUD becomes 110 USD - and the number, tax codes
     * and lines are kept. Refused once a payment is applied - see Endpoint\Invoices::update().
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

    public function withAutoNumber(): self
    {
        return $this->withInvoiceNumber(self::AUTO_NUMBER);
    }

    /**
     * Add one line to those already on the request.
     */
    public function addLineItem(InvoiceLineItemRequest $line): self
    {
        $lines = is_array($this->get('LineItems')) ? $this->get('LineItems') : [];
        $lines[] = $line->toArray();

        return $this->with('LineItems', array_values($lines));
    }

    /**
     * Email the invoice as part of this write. Saasu requires the message's From and To.
     *
     * THIS SENDS A REAL EMAIL TO A REAL CUSTOMER when the request is written.
     */
    public function withEmailToContact(EmailMessageRequest $message): self
    {
        if (!$message->has('From') || !$message->has('To')) {
            throw new InvalidArgumentException('Emailing an invoice needs the message From and To; build it with EmailMessageRequest::of().');
        }

        return $this->with('SendEmailToContact', true)->with('EmailMessage', $message);
    }

    /**
     * Apply a payment as the invoice is inserted. Refused on an update - see Endpoint\Invoices.
     */
    public function withQuickPayment(QuickPaymentRequest $payment): self
    {
        return $this->with('QuickPayment', $payment);
    }

    /**
     * Whether writing this request sends an email.
     */
    public function sendsEmail(): bool
    {
        return $this->get('SendEmailToContact') === true;
    }

    public function withTransactionType(TransactionType|string|null $transactionType): static
    {
        return $this->with('TransactionType', $transactionType instanceof \BackedEnum ? $transactionType->value : $transactionType);
    }

    public function withInvoiceType(InvoiceType|string|null $invoiceType): static
    {
        return $this->with('InvoiceType', $invoiceType instanceof \BackedEnum ? $invoiceType->value : $invoiceType);
    }

    public function withLayout(InvoiceLayout|string|null $layout): static
    {
        return $this->with('Layout', $layout instanceof \BackedEnum ? $layout->value : $layout);
    }

    public function withTransactionDate(\DateTimeInterface|string|null $transactionDate): static
    {
        return $this->putDate('TransactionDate', $transactionDate);
    }

    public function withDueDate(\DateTimeInterface|string|null $dueDate): static
    {
        return $this->putDate('DueDate', $dueDate);
    }

    public function withInvoiceNumber(?string $invoiceNumber): static
    {
        return $this->with('InvoiceNumber', $invoiceNumber);
    }

    public function withPurchaseOrderNumber(?string $purchaseOrderNumber): static
    {
        return $this->with('PurchaseOrderNumber', $purchaseOrderNumber);
    }

    public function withBillingContactId(?int $billingContactId): static
    {
        return $this->with('BillingContactId', $billingContactId);
    }

    public function withShippingContactId(?int $shippingContactId): static
    {
        return $this->with('ShippingContactId', $shippingContactId);
    }

    public function withSummary(?string $summary): static
    {
        return $this->with('Summary', $summary);
    }

    public function withNotesInternal(?string $notesInternal): static
    {
        return $this->with('NotesInternal', $notesInternal);
    }

    public function withNotesExternal(?string $notesExternal): static
    {
        return $this->with('NotesExternal', $notesExternal);
    }

    public function withTaxInclusive(?bool $taxInclusive): static
    {
        return $this->with('IsTaxInc', $taxInclusive);
    }

    public function withCurrency(?string $currency): static
    {
        return $this->with('Currency', $currency);
    }

    public function withFxRate(int|float|null $fxRate): static
    {
        return $this->with('FxRate', $fxRate);
    }

    public function withAutoPopulateFxRate(?bool $autoPopulateFxRate): static
    {
        return $this->with('AutoPopulateFxRate', $autoPopulateFxRate);
    }

    public function withRequiresFollowUp(?bool $requiresFollowUp): static
    {
        return $this->with('RequiresFollowUp', $requiresFollowUp);
    }

    public function withBrandId(?int $brandId): static
    {
        return $this->with('BrandId', $brandId);
    }

    public function withTemplateId(?int $templateId): static
    {
        return $this->with('TemplateId', $templateId);
    }

    public function withForEntityTypeId(?int $forEntityTypeId): static
    {
        return $this->with('ForEntityTypeId', $forEntityTypeId);
    }

    /**
     * @param  InvoiceTermsRequest|array<string, mixed>|null  $terms
     */
    public function withTerms(InvoiceTermsRequest|array|null $terms): static
    {
        return $this->with('Terms', $terms);
    }

    /**
     * @param  list<InvoiceLineItemRequest|array<string, mixed>>  $lineItems
     */
    public function withLineItems(array $lineItems): static
    {
        return $this->putList('LineItems', $lineItems);
    }

    /**
     * @param  list<string>  $tags
     */
    public function withTags(array $tags): static
    {
        return $this->putTags($tags);
    }

    public function withLastUpdatedId(?string $lastUpdatedId): static
    {
        return $this->with('LastUpdatedId', $lastUpdatedId);
    }
}
