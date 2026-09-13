<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Tests;

use Hampel\Saasu\Api\Entity\Invoice;
use Hampel\Saasu\Api\Entity\Journal;
use Hampel\Saasu\Api\Entity\Payment;
use Hampel\Saasu\Api\Enum\InvoiceLayout;
use Hampel\Saasu\Api\Enum\InvoiceType;
use Hampel\Saasu\Api\Enum\PdfLayout;
use Hampel\Saasu\Api\Exception\InvalidArgumentException;
use Hampel\Saasu\Api\Filter\DateRange;
use Hampel\Saasu\Api\Filter\InvoiceFilter;
use Hampel\Saasu\Api\Request\AttachmentRequest;
use Hampel\Saasu\Api\Request\EmailMessageRequest;
use Hampel\Saasu\Api\Request\InvoiceLineItemRequest;
use Hampel\Saasu\Api\Request\InvoiceRequest;
use Hampel\Saasu\Api\Request\JournalItemRequest;
use Hampel\Saasu\Api\Request\JournalRequest;
use Hampel\Saasu\Api\Request\PaymentItemRequest;
use Hampel\Saasu\Api\Request\PaymentRequest;
use Hampel\Saasu\Api\Request\QuickPaymentRequest;
use Hampel\Saasu\Api\Support\Cast;

final class TransactionsTest extends TestCase
{
    public function testAnUnpaidSalesListSendsBothFilters(): void
    {
        $this->pushFixture('invoices');

        $invoices = $this->saasu()->invoices()->list(InvoiceFilter::sales()->unpaid()->datedBetween(DateRange::financialYear(2027)));

        $this->assertSame([
            'FileId' => '123',
            'TransactionType' => 'S',
            'PaymentStatus' => 'U',
            'InvoiceFromDate' => '2026-07-01',
            'InvoiceToDate' => '2027-06-30',
            'Page' => '1',
            'PageSize' => '100',
        ], $this->sentQuery());
        $this->assertSame([5093684, 7843684], array_map(static fn (Invoice $invoice): ?int => $invoice->id, $invoices->items));
    }

    public function testASaleIsBuiltWithWhatSaasuRequires(): void
    {
        $this->pushFixture('invoice-inserted');

        $saved = $this->saasu()->invoices()->create(
            InvoiceRequest::sale(InvoiceType::TaxInvoice, InvoiceLayout::Service, new \DateTimeImmutable('2026-09-13 23:30', new \DateTimeZone('Australia/Sydney')))
                ->withBillingContactId(54353)
                ->withAutoNumber()
                ->withTaxInclusive(true)
                ->addLineItem(InvoiceLineItemRequest::service('Consulting', 1234, 1650.00, 'G1'))
        );

        $this->assertSame([
            'TransactionType' => 'S',
            'InvoiceType' => 'Tax Invoice',
            'Layout' => 'S',
            'TransactionDate' => '2026-09-13',
            'BillingContactId' => 54353,
            'InvoiceNumber' => '<Auto Number>',
            'IsTaxInc' => true,
            'LineItems' => [['Description' => 'Consulting', 'AccountId' => 1234, 'TotalAmount' => 1650.0, 'TaxCode' => 'G1']],
        ], $this->sentBody());

        $this->assertSame(321, $saved->id);
        $this->assertSame('987', $saved->generatedInvoiceNumber());
    }

    /**
     * Saasu answers an invoice update with `"Id": 0`; the id updated is the one to report.
     */
    public function testAnUpdateReportsTheIdItUpdatedWhenSaasuAnswersZero(): void
    {
        $this->pushFixture('invoice-updated');

        $saved = $this->saasu()->invoices()->update(5093684, InvoiceRequest::from(Invoice::fromArray(self::fixture('invoice'))));

        $this->assertSame(5093684, $saved->id);
    }

    /**
     * An update built from an invoice must never re-send an email or re-apply a payment, and
     * must not send what Saasu computes.
     */
    public function testAnInvoiceBuiltFromTheRecordCarriesNoInstructionsOrTotals(): void
    {
        $body = InvoiceRequest::from(Invoice::fromArray(self::fixture('invoice')))->toArray();

        foreach (['SendEmailToContact', 'EmailMessage', 'QuickPayment', 'TotalAmount', 'AmountOwed', 'BillingContactFirstName', 'InvoiceStatus', 'PaymentStatus', 'TransactionId'] as $field) {
            $this->assertArrayNotHasKey($field, $body);
        }

        $this->assertSame('AAAAAAAKgc=', $body['LastUpdatedId']);
        $this->assertSame('2015-01-24T00:00:00', $body['TransactionDate']);
        $this->assertCount(1, Cast::array($body['LineItems']));
    }

    public function testAQuickPaymentIsRefusedOnAnUpdate(): void
    {
        $request = InvoiceRequest::from(Invoice::fromArray(self::fixture('invoice')))
            ->withQuickPayment(QuickPaymentRequest::of('2026-09-13', 1858, 50.00));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('only when an invoice is inserted');

        $this->saasu()->invoices()->update(5093684, $request);
    }

    public function testEmailingWithAWriteNeedsFromAndTo(): void
    {
        $request = InvoiceRequest::sale(InvoiceType::TaxInvoice, InvoiceLayout::Service, '2026-09-13')
            ->withEmailToContact(EmailMessageRequest::of('accounts@example.com', 'joe@example.com', 'Your invoice'));

        $this->assertTrue($request->sendsEmail());
        $this->assertSame(['From' => 'accounts@example.com', 'To' => 'joe@example.com', 'Subject' => 'Your invoice', 'Body' => null], $request->get('EmailMessage'));

        $this->expectException(InvalidArgumentException::class);

        InvoiceRequest::make()->withEmailToContact(EmailMessageRequest::make()->withTo('joe@example.com'));
    }

    public function testEmailingAnInvoiceAnswersWithSaasusMessage(): void
    {
        $this->pushFixture('invoice-emailed');

        $message = $this->saasu()->invoices()->email(123, 'joe@example.com');

        $this->assertSame('/Invoice/123/email', $this->sentPath());
        $this->assertSame(['EmailTo' => 'joe@example.com'], $this->sentBody());
        $this->assertSame('Invoice has been emailed.', $message);
    }

    public function testAPdfCanNameItsTemplateAndLayout(): void
    {
        $this->client->pushRaw(200, '%PDF-1.7');

        $this->saasu()->invoices()->pdf(5093684, 12, PdfLayout::ShippingSlip);

        $this->assertSame(['FileId' => '123', 'TemplateId' => '12', 'PrintAs' => '98'], $this->sentQuery());
    }

    public function testTheSalesSummaryReadsItsTotals(): void
    {
        $this->pushFixture('sales-summary');

        $summary = $this->saasu()->invoices()->salesSummary(DateRange::between('2026-07-01', '2026-09-30'));

        $this->assertSame('/Invoices/SalesStatsSummary', $this->sentPath());
        $this->assertSame(2.0, $summary->totalOwed);
        $this->assertSame(11, $summary->totalCount);
    }

    public function testAttachmentsAreListedAndUploadedAsBase64(): void
    {
        $this->pushFixture('attachments');
        $this->client->pushJson(200, ['Id' => 123, '_links' => []]);

        $saasu = $this->saasu();
        $listed = $saasu->invoices()->attachments(16845);
        $id = $saasu->attachments()->create(AttachmentRequest::of(16845, 'remittance.txt', 'paid in full'));

        $this->assertSame(['File attachment 1.doc', 'Toy sale.doc'], array_map(static fn ($info) => $info->name, $listed));
        $this->assertSame(123, $id);
        $this->assertSame([
            'ItemIdAttachedTo' => 16845,
            'Name' => 'remittance.txt',
            'AttachmentData' => base64_encode('paid in full'),
            'Description' => null,
        ], $this->sentBody());
        $this->assertStringNotContainsString(base64_encode('paid in full'), print_r(AttachmentRequest::of(1, 'a', 'paid in full'), true));
    }

    public function testAPaymentIsBuiltAgainstTheInvoicesItSettles(): void
    {
        $this->client->pushJson(200, ['InsertedEntityId' => 321, 'LastUpdatedId' => 'AAAKyf=', 'UtcLastModified' => '2026-09-02T19:00:11Z']);

        $saved = $this->saasu()->payments()->create(PaymentRequest::received('2026-09-13', 1858, [PaymentItemRequest::of(5093684, 110.00)]));

        $this->assertSame([
            'TransactionType' => 'SP',
            'TransactionDate' => '2026-09-13',
            'PaymentAccountId' => 1858,
            'PaymentItems' => [['InvoiceTransactionId' => 5093684, 'AmountPaid' => 110.0]],
        ], $this->sentBody());
        $this->assertSame(321, $saved->id);
    }

    /**
     * Saasu answers a payment update with no id at all.
     */
    public function testAPaymentUpdateWithNoIdInTheAnswerReportsTheIdUpdated(): void
    {
        $this->pushFixture('payment-updated');

        $payment = Payment::fromArray(['LastUpdatedId' => 'x'] + self::fixture('payment'));
        $saved = $this->saasu()->payments()->update(324, PaymentRequest::from($payment));

        $this->assertSame(324, $saved->id);
    }

    public function testPaymentsForAnInvoiceUseTheForInvoiceFilter(): void
    {
        $this->pushFixture('payments');

        $payments = $this->saasu()->payments()->forInvoice(5093684);

        $this->assertSame('5093684', $this->sentQuery()['ForInvoiceId']);
        $this->assertSame(6, $payments[0]->id);
    }

    public function testAJournalRequestKnowsWhetherItBalances(): void
    {
        $balanced = JournalRequest::of('2026-09-13', [JournalItemRequest::debit(1, 100.0), JournalItemRequest::credit(2, 100.0)]);
        $unbalanced = JournalRequest::of('2026-09-13', [JournalItemRequest::debit(1, 100.0), JournalItemRequest::credit(2, 90.0)]);

        $this->assertSame(0.0, $balanced->imbalance());
        $this->assertSame(10.0, $unbalanced->imbalance());
    }

    /**
     * Saasu's sample journal insert answers with both an inserted and an updated id.
     */
    public function testAJournalInsertReportsTheInsertedId(): void
    {
        $this->pushFixture('journal-inserted');

        $saved = $this->saasu()->journals()->create(JournalRequest::of('2026-09-13', [JournalItemRequest::debit(1, 1.0)]));

        $this->assertSame(1, $saved->id);
    }

    public function testAJournalReadsItsLines(): void
    {
        $this->pushFixture('journal');

        $journal = $this->saasu()->journals()->get(5093684);

        $this->assertInstanceOf(Journal::class, $journal);
        $this->assertSame(321, $journal->items[0]->accountId);
    }
}
