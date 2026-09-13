<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Tests;

use Hampel\Saasu\Api\Authentication\AccessToken;
use Hampel\Saasu\Api\Config;
use Hampel\Saasu\Api\Endpoint\Endpoint;
use Hampel\Saasu\Api\Entity\Contact;
use Hampel\Saasu\Api\Entity\Item;
use Hampel\Saasu\Api\Entity\LeaveRequest;
use Hampel\Saasu\Api\Enum\SearchScope;
use Hampel\Saasu\Api\Enum\SearchTransactionType;
use Hampel\Saasu\Api\Exception\InvalidArgumentException;
use Hampel\Saasu\Api\Exception\NotAuthenticatedException;
use Hampel\Saasu\Api\Exception\TwoFactorRequiredException;
use Hampel\Saasu\Api\Filter\DateRange;
use Hampel\Saasu\Api\Filter\DeletedEntityFilter;
use Hampel\Saasu\Api\Filter\EmployeeFilter;
use Hampel\Saasu\Api\Filter\ReportFilter;
use Hampel\Saasu\Api\Request\BuildItemRequest;
use Hampel\Saasu\Api\Request\ItemRequest;
use Hampel\Saasu\Api\Request\LeaveRequestItemRequest;
use Hampel\Saasu\Api\Request\LeaveRequestRequest;
use Hampel\Saasu\Api\Request\TimesheetRequest;
use Hampel\Saasu\Api\Request\UserRequest;
use Hampel\Saasu\Api\Result\Page;

final class EverythingElseTest extends TestCase
{
    public function testTheBankBalancesAreRead(): void
    {
        $this->pushFixture('bank-balances');

        $balances = $this->saasu()->accounts()->bankBalances();

        $this->assertSame('/Accounts/BankAccountBalances', $this->sentPath());
        $this->assertSame(1.0, $balances[0]->balance);
    }

    public function testAHeaderAccountIsNotPostable(): void
    {
        $this->pushFixture('accounts');

        $accounts = $this->saasu()->accounts()->all();

        $this->assertTrue($accounts[0]->isPostable());
        $this->assertFalse($accounts[2]->isPostable());
        $this->assertSame('010101', $accounts[1]->bsb);
    }

    /**
     * Saasu's own tests show a combo refused without components and a sold item refused
     * without an income account; the builders take what each rule needs.
     */
    public function testAComboNeedsComponentsAndASoldItemItsIncomeAccount(): void
    {
        $item = ItemRequest::inventory('ABC', 'Widget')->sold(789, 20.99, 112);

        $this->assertSame([
            'Type' => 'I',
            'Code' => 'ABC',
            'Description' => 'Widget',
            'IsSold' => true,
            'SaleIncomeAccountId' => 789,
            'SellingPrice' => 20.99,
            'SaleTaxCodeId' => 112,
            'IsSellingPriceIncTax' => true,
        ], $item->toArray());

        $this->assertSame([['Id' => 1, 'Quantity' => 5.0]], ItemRequest::combo('CBO', 'Kit', [BuildItemRequest::of(1, 5)])->get('BuildItems'));

        $this->expectException(InvalidArgumentException::class);

        ItemRequest::combo('CBO', 'Kit', []);
    }

    public function testAnItemBuiltFromTheRecordLeavesOutComputedStock(): void
    {
        $body = ItemRequest::from(Item::fromArray(self::fixture('item')))->toArray();

        foreach (['StockOnHand', 'CurrentValue', 'OnOrder', 'Committed', 'Id', 'LastModifiedBy'] as $computed) {
            $this->assertArrayNotHasKey($computed, $body);
        }

        $this->assertSame('AAAAAKgc=', $body['LastUpdatedId']);
    }

    public function testBuildingAComboPostsTheQuantity(): void
    {
        $this->pushFixture('item-built');

        $message = $this->saasu()->items()->build(123, 1);

        $this->assertSame('/Item/123/build', $this->sentPath());
        $this->assertSame('{"Quantity":1.0}', (string) $this->client->lastRequest()->getBody());
        $this->assertSame('1 combo item(s) have been built.', $message);
    }

    /**
     * The transfer list's key is `Transfers`, not `ItemTransfers`.
     */
    public function testTheTransferListIsReadFromItsOwnKey(): void
    {
        $this->pushFixture('item-transfers');

        $this->assertCount(2, $this->saasu()->itemTransfers()->list());
    }

    public function testDeletedEntitiesAreReadFromEntities(): void
    {
        $this->pushFixture('deleted-entities');

        $deleted = $this->saasu()->deletedEntities()->list(DeletedEntityFilter::all()->deletedBetween(DateRange::on('2026-09-02')));

        $this->assertSame('2026-09-02', $this->sentQuery()['UtcDeletedFromDate']);
        $this->assertSame(111, $deleted->first()?->entityId);
    }

    public function testTheFileListIsSentWithoutAFileId(): void
    {
        $this->pushFixture('file-identities');

        $files = $this->saasu(new Config())->files()->all();

        $this->assertSame(['Page' => '1', 'PageSize' => '100'], $this->sentQuery());
        $this->assertSame([1, 2], array_map(static fn ($file) => $file->id, $files));
    }

    public function testVerifyReadsTheConfiguredFile(): void
    {
        $this->pushFixture('file-identity');

        $identity = $this->saasu()->verify();

        $this->assertSame('/FileIdentity', $this->sentPath());
        $this->assertSame('Some Legal Name', $identity->fullLegalName);
    }

    public function testATokenRequestSendsNoCredentialAndReadsTheGrant(): void
    {
        $this->pushFixture('token');

        $grant = $this->saasu()->authorisation()->token('api@example.com', 'secret');

        $this->assertFalse($this->client->lastRequest()->hasHeader('Authorization'));
        $this->assertSame([15431, 14078, 2045], $grant->fileIds());
    }

    /**
     * Measured 2026-09-13 against the live token endpoint.
     */
    public function testBadCredentialsAreNamedByTheOauthError(): void
    {
        $this->client->pushJson(401, ['error' => 'invalid_credentials', 'error_description' => 'Invalid username or password', 'data' => null, 'is_2fa_code_required' => false, 'is_2fa_code_sent' => false]);

        try {
            $this->saasu()->authorisation()->token('nobody@example.invalid', 'wrong');

            $this->fail('expected a NotAuthenticatedException');
        } catch (NotAuthenticatedException $e) {
            $this->assertNotInstanceOf(TwoFactorRequiredException::class, $e);
            $this->assertSame('invalid_credentials', $e->oauthError());
            $this->assertStringContainsString('Invalid username or password', $e->getMessage());
        }
    }

    public function testTwoFactorSaysWhetherTheCodeWasSent(): void
    {
        $this->client->pushJson(401, ['error' => '2fa_code_required', 'error_description' => 'Two-factor code required', 'is_2fa_code_required' => true, 'is_2fa_code_sent' => true]);

        try {
            $this->saasu()->authorisation()->tokenWithTwoFactor('api@example.com', 'secret');

            $this->fail('expected a TwoFactorRequiredException');
        } catch (TwoFactorRequiredException $e) {
            $this->assertTrue($e->codeWasSent());
            $this->assertSame('/authorisation/token-2fa', $this->sentPath());
        }
    }

    public function testPingAnswersFalseRatherThanRaisingForARefusedToken(): void
    {
        $this->client->pushRaw(200, 'true');
        $this->client->pushRaw(401, '');

        $saasu = $this->saasu(authentication: new AccessToken('token'));

        $this->assertTrue($saasu->authorisation()->ping());
        $this->assertFalse($saasu->authorisation()->ping());
    }

    public function testTheUserIsNotFileScopedAndAPasswordResetSendsNoCredential(): void
    {
        $this->pushFixture('user');
        $this->client->pushJson(200, ['StatusMessage' => 'A password reset email has been sent.', '_links' => []]);

        $saasu = $this->saasu();
        $user = $saasu->user()->get();
        $this->assertSame('', $this->client->lastRequest()->getUri()->getQuery());
        $this->assertTrue($user->twoFactorEnabled);

        $this->assertSame('A password reset email has been sent.', $saasu->user()->resetPassword('api@example.com'));
        $this->assertFalse($this->client->lastRequest()->hasHeader('Authorization'));
    }

    public function testAUserUpdateNeedsAFirstName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->saasu()->user()->update(UserRequest::make()->withLastName('Blogs'));
    }

    public function testSearchSendsItsScopeAndTurnsHighlightingOff(): void
    {
        $this->pushFixture('search');

        $results = $this->saasu()->search()->search('toys', SearchScope::Transactions, SearchTransactionType::Sale, pageSize: 25);

        $this->assertSame([
            'FileId' => '123',
            'Keywords' => 'toys',
            'Scope' => 'Transactions',
            'TransactionType' => 'Transactions.Sale',
            'IncludeSearchTermHighlights' => 'false',
            'Page' => '1',
            'PageSize' => '25',
        ], $this->sentQuery());
        $this->assertSame(3224.89, $results->transactions[0]->totalAmountIncTax);
        $this->assertSame(2, $results->totalItems);
        $this->assertFalse($results->hasMore());
        $this->assertSame(4, $results->total());
    }

    public function testATransactionTypeWithoutTheTransactionScopeIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->saasu()->search()->search('toys', SearchScope::All, SearchTransactionType::Sale);
    }

    public function testAProfitAndLossReportSendsItsPeriods(): void
    {
        $this->pushFixture('profit-and-loss');

        $report = $this->saasu()->reports()->profitAndLoss(
            ReportFilter::all()->period(DateRange::financialYear(2027))->comparedTo(DateRange::financialYear(2026))->cashBasis()
        );

        $this->assertSame('Cash', $this->sentQuery()['AccountingMethod']);
        $this->assertSame('2025-07-01', $this->sentQuery()['ComparisonPeriodFromDate']);
        $this->assertCount(2, $report->incomes);
        $this->assertSame(2.0, $report->netProfit?->amount);
    }

    public function testLookupsAreReadFromData(): void
    {
        $this->pushFixture('currencies');

        $currencies = $this->saasu()->lookups()->currencies();

        $this->assertSame('/LookupData/Currencies', $this->sentPath());
        $this->assertSame('sample string 1', $currencies[0]->key());
    }

    /**
     * The employee read is the one single record Saasu wraps.
     */
    public function testAnEmployeeIsUnwrappedAndLeaveBalancesAreAskedFor(): void
    {
        $this->pushFixture('employee');

        $employee = $this->saasu()->employees()->get(1, EmployeeFilter::all()->withLeaveBalances('2026-09-13'));

        $this->assertSame(['FileId' => '123', 'IncludeLeaveBalances' => 'true', 'LeaveBalanceAsAtDate' => '2026-09-13'], $this->sentQuery());
        $this->assertSame(1, $employee->id);
        $this->assertCount(2, $employee->leaveBalances);
    }

    public function testPayrollEntriesKeepTheirTotals(): void
    {
        $this->pushFixture('payroll-entries');

        $page = $this->saasu()->payrollEntries()->list();

        $this->assertEquals(1.0, $page->extra['TotalWages']);
        $this->assertSame(4.0, $page->items[0]->wages);
        $this->assertSame(1, $page->items[0]->employee?->id);
    }

    public function testATimesheetCannotFinishBeforeItStarts(): void
    {
        $request = TimesheetRequest::of(7, new \DateTimeImmutable('2026-09-13 09:00'), new \DateTimeImmutable('2026-09-13 17:00'), 30);

        $this->assertSame(['ContactId' => 7, 'StartTime' => '2026-09-13T09:00:00', 'FinishTime' => '2026-09-13T17:00:00', 'BreakDurationInMinutes' => 30], $request->toArray());

        $this->expectException(InvalidArgumentException::class);

        TimesheetRequest::of(7, new \DateTimeImmutable('2026-09-13 17:00'), new \DateTimeImmutable('2026-09-13 09:00'));
    }

    /**
     * A leave request's concurrency check is its timestamp, carried back exactly as it arrived.
     */
    public function testALeaveRequestUpdateNeedsItsTimestampRatherThanAnId(): void
    {
        $leave = LeaveRequest::fromArray(self::fixture('leave-request'));
        $request = LeaveRequestRequest::from($leave)->withItems([LeaveRequestItemRequest::of('2026-09-14', 7.6)]);

        $this->assertSame('2026-09-13T09:23:31.2566808+10:00', $request->get('LastModifiedDateUtc'));

        $this->pushFixture('leave-request-inserted');
        $this->saasu()->leaveRequests()->update(1, $request);
        $this->assertSame('/Payroll/LeaveRequest/1', $this->sentPath());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('LastModifiedDateUtc');

        $this->saasu()->leaveRequests()->update(1, LeaveRequestRequest::of(5, 4, '2026-09-14', '2026-09-15'));
    }

    public function testLeaveCannotEndBeforeItStarts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        LeaveRequestRequest::of(5, 4, '2026-09-15', '2026-09-14');
    }

    public function testDerivedClientsShareTheRequestCount(): void
    {
        $this->pushFixture('contact');
        $this->pushFixture('contact');

        $saasu = $this->saasu(new Config(self::FILE_ID, requestBudget: 5));
        $other = $saasu->withFileId(456);

        $saasu->contacts()->get(1);
        $other->contacts()->get(1);

        $this->assertSame('456', $this->sentQuery()['FileId']);
        $this->assertSame(2, $saasu->requestsSent());
        $this->assertSame(2, $other->requestsSent());
    }

    public function testTheExtensionPointConstructsAnyEndpoint(): void
    {
        $this->pushFixture('contacts');

        $saasu = $this->saasu();
        $endpoint = $saasu->endpoint(CustomersEndpoint::class);

        $this->assertSame($endpoint, $saasu->endpoint(CustomersEndpoint::class));
        $this->assertCount(2, $endpoint->customers());
        $this->assertSame('true', $this->sentQuery()['IsCustomer']);
    }

    public function testAnAbstractEndpointIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Endpoint::assertConstructible(Endpoint::class);
    }
}

final class CustomersEndpoint extends Endpoint
{
    /**
     * @return Page<Contact>
     */
    public function customers(): Page
    {
        return $this->apiPaginate('Contacts', 'Contacts', Contact::fromArray(...), ['IsCustomer' => true]);
    }
}
