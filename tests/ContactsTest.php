<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Tests;

use Hampel\Saasu\Api\Entity\Contact;
use Hampel\Saasu\Api\Exception\InvalidArgumentException;
use Hampel\Saasu\Api\Exception\NotFoundException;
use Hampel\Saasu\Api\Filter\ContactFilter;
use Hampel\Saasu\Api\Filter\DateRange;
use Hampel\Saasu\Api\Request\AddressRequest;
use Hampel\Saasu\Api\Request\AggregateCompanyRequest;
use Hampel\Saasu\Api\Request\ContactAggregateRequest;
use Hampel\Saasu\Api\Request\ContactRequest;
use Hampel\Saasu\Api\Request\TradingTermsRequest;

final class ContactsTest extends TestCase
{
    public function testAListSendsTheFilterAndAlwaysNamesThePage(): void
    {
        $this->pushFixture('contacts');

        $page = $this->saasu()->contacts()->list(ContactFilter::all()->customers()->active());

        $this->assertSame('/Contacts', $this->sentPath());
        $this->assertSame(['FileId' => '123', 'IsCustomer' => 'true', 'IsActive' => 'true', 'Page' => '1', 'PageSize' => '100'], $this->sentQuery());
        $this->assertCount(2, $page);
        $this->assertInstanceOf(Contact::class, $page->first());
        $this->assertFalse($page->hasMore(), 'two items against a page size of 100 is the last page');
    }

    /**
     * Saasu gives no total, so the walk ends at the first page that is not full - and a list
     * that is an exact multiple of the page size costs one empty request to finish.
     */
    public function testAWalkStopsAtTheFirstShortPage(): void
    {
        $row = self::fixture('contact');
        $this->client->pushJson(200, ['Contacts' => [$row, $row]]);
        $this->client->pushJson(200, ['Contacts' => [$row, $row]]);
        $this->client->pushJson(200, ['Contacts' => []]);

        $contacts = $this->saasu()->contacts()->each(pageSize: 2);

        $this->assertCount(4, iterator_to_array($contacts, false));
        $this->assertCount(3, $this->client->requests);
        $this->assertSame('3', $this->sentQuery()['Page']);
    }

    public function testStoppingAWalkEarlyStopsTheRequests(): void
    {
        $row = self::fixture('contact');
        $this->client->pushJson(200, ['Contacts' => [$row, $row]]);

        foreach ($this->saasu()->contacts()->each(pageSize: 2) as $contact) {
            break;
        }

        $this->assertCount(1, $this->client->requests);
    }

    public function testFindAnswersNullForAMissingRecord(): void
    {
        $this->client->pushRaw(404, '');

        $this->assertNull($this->saasu()->contacts()->find(99999999));
    }

    /**
     * An HTML 404 is a path Saasu does not route - measured - and must not read as "no record".
     */
    public function testFindRaisesForAnUnroutedPath(): void
    {
        $this->client->pushRaw(404, '<!DOCTYPE html><html><title>404 - File or directory not found.</title></html>');

        $this->expectException(NotFoundException::class);

        $this->saasu()->contacts()->find(1);
    }

    public function testCreateSendsOnlyTheFieldsItWasGiven(): void
    {
        $this->pushFixture('contact-inserted');

        $saved = $this->saasu()->contacts()->create(
            ContactRequest::person('Joe', 'Blogs')
                ->asCustomer()
                ->withEmailAddress('joe@example.com')
                ->withContactReference('CUST-1')
                ->withPostalAddress(AddressRequest::of('1 Test St', 'Sydney', 'NSW', '2000', 'Australia'))
                ->withSaleTradingTerms(TradingTermsRequest::dueIn(14))
                ->withTags(['VIP', ' '])
        );

        $this->assertSame('POST', $this->sentMethod());
        $this->assertSame('/Contact', $this->sentPath());
        $this->assertSame([
            'GivenName' => 'Joe',
            'FamilyName' => 'Blogs',
            'IsCustomer' => true,
            'EmailAddress' => 'joe@example.com',
            'ContactId' => 'CUST-1',
            'PostalAddress' => ['Street' => '1 Test St', 'City' => 'Sydney', 'State' => 'NSW', 'Postcode' => '2000', 'Country' => 'Australia'],
            'SaleTradingTerms' => ['TradingTermsType' => 1, 'TradingTermsInterval' => 14, 'TradingTermsIntervalType' => 1],
            'Tags' => ['VIP'],
        ], $this->sentBody());

        $this->assertSame(123, $saved->id);
        $this->assertSame('AAAAAFwWAN8=', $saved->lastUpdatedId);
    }

    /**
     * An update built from the record carries everything writable and its LastUpdatedId - and
     * none of what Saasu computes.
     */
    public function testAnUpdateBuiltFromTheRecordCarriesItsWritableFieldsAndConcurrencyId(): void
    {
        $contact = Contact::fromArray(self::fixture('contact'));
        $this->pushFixture('contact-updated');

        $saved = $this->saasu()->contacts()->update($contact->id ?? 0, ContactRequest::from($contact)->withEmailAddress('new@example.com'));

        $body = $this->sentBody();

        $this->assertSame('PUT', $this->sentMethod());
        $this->assertSame('/Contact/54353', $this->sentPath());
        $this->assertSame('AAAAAFwWAN8=', $body['LastUpdatedId']);
        $this->assertSame('new@example.com', $body['EmailAddress']);
        $this->assertSame('Blogs', $body['FamilyName']);
        $this->assertSame(['Advisor', 'Business'], $body['Tags']);

        // Measured 2026-09-14: an update that omits CreatedDateUtc erases the contact's creation date.
        $this->assertSame('2026-09-02T19:00:11.9589518Z', $body['CreatedDateUtc']);

        foreach (['Id', 'LastModifiedDateUtc', 'LastModifiedByUserId', 'OcrRecipientAlias', '_links'] as $readOnly) {
            $this->assertArrayNotHasKey($readOnly, $body);
        }

        $this->assertSame(123, $saved->id);
    }

    public function testAnUpdateWithoutALastUpdatedIdIsRefusedBeforeSending(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ContactRequest::from()');

        try {
            $this->saasu()->contacts()->update(1, ContactRequest::person('Joe'));
        } finally {
            $this->assertSame([], $this->client->requests);
        }
    }

    public function testDeleteSendsADelete(): void
    {
        $this->pushFixture('deleted');

        $this->saasu()->contacts()->delete(54353);

        $this->assertSame('DELETE', $this->sentMethod());
        $this->assertSame('/Contact/54353', $this->sentPath());
    }

    public function testFirstWithEmailAsksForOneItem(): void
    {
        $this->pushFixture('contacts');

        $contact = $this->saasu()->contacts()->firstWithEmail('joe@example.com');

        $this->assertSame(54353, $contact?->id);
        $this->assertSame('joe@example.com', $this->sentQuery()['Email']);
        $this->assertSame('1', $this->sentQuery()['PageSize']);
    }

    public function testFirstWithReferenceAsksForOneItem(): void
    {
        $this->pushFixture('contacts');

        $contact = $this->saasu()->contacts()->firstWithReference('XF-1234');

        $this->assertSame(54353, $contact?->id);
        $this->assertSame('XF-1234', $this->sentQuery()['ContactId']);
        $this->assertSame('1', $this->sentQuery()['PageSize']);
    }

    public function testFirstWithReferenceAnswersNullWhenNothingMatches(): void
    {
        $this->client->pushJson(200, ['Contacts' => []]);

        $this->assertNull($this->saasu()->contacts()->firstWithReference('XF-0'));
    }

    public function testAStatementIsAPdfForAPeriod(): void
    {
        $this->client->pushRaw(200, '%PDF-1.7', ['Content-Type' => 'application/pdf']);

        $this->saasu()->contacts()->statement(54353, DateRange::between('2026-07-01', '2026-09-30'));

        $this->assertSame('/Contact/54353/generate-pdf', $this->sentPath());
        $this->assertSame(['FileId' => '123', 'GenerateType' => 'Statement', 'FromDate' => '2026-07-01', 'ToDate' => '2026-09-30'], $this->sentQuery());
    }

    public function testAnIdMustBePositive(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->saasu()->contacts()->get(0);
    }

    public function testACompanyUpdateRoundTripsFromTheRecord(): void
    {
        $this->pushFixture('company');
        $this->client->pushJson(200, ['UpdatedCompanyId' => 1, 'LastUpdatedId' => 'new', 'LastModified' => '2026-09-13T00:00:00Z']);

        $saasu = $this->saasu();
        $company = $saasu->companies()->get(1);
        $saved = $saasu->companies()->update(1, \Hampel\Saasu\Api\Request\CompanyRequest::from($company)->withTradingName('Renamed'));

        $this->assertSame('Renamed', $this->sentBody()['TradingName']);
        $this->assertSame('AAAAAAKbc=', $this->sentBody()['LastUpdatedId']);
        $this->assertSame('new', $saved->lastUpdatedId);
        $this->assertSame('2026-09-13', $saved->lastModified?->format('Y-m-d'));
    }

    /**
     * An aggregate write can insert or update three records; the answer says which.
     */
    public function testAnAggregateInsertReportsTheCompanyAndManagerItTouched(): void
    {
        $this->pushFixture('contact-aggregate-inserted');

        $saved = $this->saasu()->contactAggregates()->create(
            ContactAggregateRequest::person('Joe', 'Blogs')->withCompany(AggregateCompanyRequest::make()->withName('ACME Co'))
        );

        $this->assertSame('/ContactAggregate', $this->sentPath());
        $this->assertSame(['Name' => 'ACME Co'], $this->sentBody()['Company']);
        $this->assertSame(234, $saved->id);
        $this->assertSame(789, $saved->companyId());
        $this->assertSame(233, $saved->contactManagerId());
    }

    public function testAnAggregateReadsItsNestedCompanyAndManager(): void
    {
        $this->pushFixture('contact-aggregate');

        $aggregate = $this->saasu()->contactAggregates()->get(54353);

        $this->assertSame('ACME Co', $aggregate->company?->name);
        $this->assertSame('Joanne', $aggregate->contactManager?->givenName);
    }
}
