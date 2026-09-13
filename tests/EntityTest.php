<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Tests;

use Hampel\Saasu\Api\Entity\Account;
use Hampel\Saasu\Api\Entity\Activity;
use Hampel\Saasu\Api\Entity\Attachment;
use Hampel\Saasu\Api\Entity\Company;
use Hampel\Saasu\Api\Entity\Contact;
use Hampel\Saasu\Api\Entity\ContactAggregate;
use Hampel\Saasu\Api\Entity\Entity;
use Hampel\Saasu\Api\Entity\FileIdentity;
use Hampel\Saasu\Api\Entity\Invoice;
use Hampel\Saasu\Api\Entity\Item;
use Hampel\Saasu\Api\Entity\ItemAdjustment;
use Hampel\Saasu\Api\Entity\ItemTransfer;
use Hampel\Saasu\Api\Entity\Journal;
use Hampel\Saasu\Api\Entity\LeaveRequest;
use Hampel\Saasu\Api\Entity\Payment;
use Hampel\Saasu\Api\Entity\TaxCode;
use Hampel\Saasu\Api\Entity\Timesheet;
use Hampel\Saasu\Api\Entity\User;
use Hampel\Saasu\Api\Enum\InvoiceLayout;
use Hampel\Saasu\Api\Enum\InvoiceType;
use Hampel\Saasu\Api\Enum\TradingTermsIntervalType;
use Hampel\Saasu\Api\Enum\TradingTermsType;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Every entity against the sample Saasu's own documentation publishes for it.
 *
 * The check that matters is the generic one: every field the sample carries with a value arrives
 * on the entity with a value. It is what catches a field that was never mapped, or mapped under
 * the wrong wire name - neither of which any hand-written assertion would think to look for.
 */
final class EntityTest extends TestCase
{
    /**
     * Sample fields deliberately not given a property - with the reason.
     */
    private const UNMAPPED = [
        '_links' => 'read through links()',
        // Documented as deprecated and read-only; still on raw.
        'DateTimeFormatId' => 'deprecated',
        'NumberFormatId' => 'deprecated',
        'IncludePendingTransactions' => 'mapped, but deprecated and null in every sample except one',
        // Request-only fields Saasu echoes as null in its invoice samples.
        'SendEmailToContact' => 'an instruction, not state',
        'EmailMessage' => 'an instruction, not state',
        'QuickPayment' => 'an instruction, not state',
        'ForEntityTypeId' => 'print setting on a write',
    ];

    /**
     * @return iterable<string, array{class-string<Entity>, string}>
     */
    public static function samples(): iterable
    {
        yield 'account' => [Account::class, 'account'];
        yield 'activity' => [Activity::class, 'activity'];
        yield 'attachment' => [Attachment::class, 'attachment'];
        yield 'company' => [Company::class, 'company'];
        yield 'contact' => [Contact::class, 'contact'];
        yield 'contact aggregate' => [ContactAggregate::class, 'contact-aggregate'];
        yield 'file identity' => [FileIdentity::class, 'file-identity'];
        yield 'invoice' => [Invoice::class, 'invoice'];
        yield 'item' => [Item::class, 'item'];
        yield 'item adjustment' => [ItemAdjustment::class, 'item-adjustment'];
        yield 'item transfer' => [ItemTransfer::class, 'item-transfer'];
        yield 'journal' => [Journal::class, 'journal'];
        yield 'leave request' => [LeaveRequest::class, 'leave-request'];
        yield 'payment' => [Payment::class, 'payment'];
        yield 'tax code' => [TaxCode::class, 'tax-code'];
        yield 'timesheet' => [Timesheet::class, 'timesheet'];
        yield 'user' => [User::class, 'user'];
    }

    /**
     * @param  class-string<Entity>  $class
     */
    #[DataProvider('samples')]
    public function testEveryDocumentedFieldWithAValueArrivesWithAValue(string $class, string $fixture): void
    {
        $sample = self::fixture($fixture);
        /** @var Entity $entity */
        $entity = $class::fromArray($sample);

        $populated = 0;

        foreach ((new \ReflectionClass($entity))->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getName() === 'raw') {
                continue;
            }

            $value = $property->getValue($entity);

            if ($value !== null && $value !== []) {
                $populated++;
            }
        }

        $expected = 0;

        foreach ($sample as $field => $value) {
            if (!isset(self::UNMAPPED[$field]) && $value !== null && $value !== [] && $value !== '') {
                $expected++;
            }
        }

        $this->assertSame($expected, $populated, sprintf(
            '%s mapped %d of the %d populated fields in Saasu\'s sample - one was dropped or read under the wrong name',
            $class,
            $populated,
            $expected
        ));

        $this->assertSame($sample, $entity->jsonSerialize(), 'an entity serialises as what Saasu sent');
    }

    public function testAContactNamesItsReferenceSoItCannotBeMistakenForItsId(): void
    {
        $contact = Contact::fromArray(self::fixture('contact'));

        $this->assertSame(54353, $contact->id);
        $this->assertSame('1234', $contact->contactReference);
        $this->assertSame('Joe Blogs', $contact->fullName());
        $this->assertCount(3, $contact->additionalEmails());
        $this->assertSame('Sydney', $contact->postalAddress?->city);
        $this->assertSame(TradingTermsType::DueIn, $contact->saleTradingTerms?->termsType());
        $this->assertSame(3, $contact->links()->related('Company')?->id());
    }

    public function testAnInvoiceReadsItsDateAsADateAndItsTypesAsEnums(): void
    {
        $invoice = Invoice::fromArray(self::fixture('invoice'));

        $this->assertSame(5093684, $invoice->id);
        $this->assertSame('2015-01-24', $invoice->transactionDate?->format('Y-m-d'));
        $this->assertTrue($invoice->isSale());
        $this->assertSame(InvoiceType::TaxInvoice, $invoice->type());
        $this->assertSame(InvoiceLayout::Item, $invoice->layout());
        $this->assertFalse($invoice->isPaid());
        $this->assertTrue($invoice->isOverdue(new \DateTimeImmutable('2026-01-01')) === false, 'no due date, so not overdue');
        $this->assertCount(1, $invoice->lineItems);
        $this->assertSame('G1', $invoice->lineItems[0]->taxCode);
        $this->assertSame(['Note', 'Supplier', 'Toys'], $invoice->tags);
    }

    /**
     * Saasu's own sample has the number null and the name set.
     */
    public function testInvoiceTermsFallBackToTheNameWhenTheNumberIsMissing(): void
    {
        $terms = Invoice::fromArray(self::fixture('invoice'))->terms;

        $this->assertNotNull($terms);
        $this->assertSame(TradingTermsIntervalType::Week, $terms->termsIntervalType());
        $this->assertSame(TradingTermsType::DueIn, $terms->termsType());
    }

    public function testAJournalKnowsWhetherItBalances(): void
    {
        $journal = Journal::fromArray(self::fixture('journal'));

        $this->assertSame(-10.0, $journal->imbalance(), 'the sample is a single credit');
    }

    public function testAnAttachmentDecodesEitherByteForm(): void
    {
        $this->assertSame('hello', Attachment::fromArray(['AttachmentData' => base64_encode('hello')])->content());
        $this->assertSame('hi', Attachment::fromArray(['AttachmentData' => [104, 105]])->content());
        $this->assertNull(Attachment::fromArray(self::fixture('attachment'))->content());
    }

    public function testATimesheetCountsItsMinutesLessTheBreak(): void
    {
        $timesheet = Timesheet::fromArray([
            'ContactId' => 7,
            'StartTime' => '2026-09-13T09:00:00',
            'FinishTime' => '2026-09-13T17:30:00',
            'BreakDurationInMinutes' => 30,
        ]);

        $this->assertSame(7, $timesheet->employeeId);
        $this->assertSame(480, $timesheet->workedMinutes());
    }

    public function testAnItemReportsWhenItNeedsReordering(): void
    {
        $item = Item::fromArray(['IsInventoried' => true, 'MinimumStockLevel' => 5, 'StockOnHand' => 5.0]);

        $this->assertTrue($item->needsReorder());
        $this->assertFalse(Item::fromArray(self::fixture('item'))->needsReorder());
    }

    public function testATaxCodeSplitsACombinedCode(): void
    {
        $this->assertSame(['G1', 'G2'], TaxCode::fromArray(['Code' => 'G1,G2'])->codes());
    }
}
