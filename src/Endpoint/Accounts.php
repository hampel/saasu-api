<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Endpoint;

use Hampel\Saasu\Api\Entity\Account;
use Hampel\Saasu\Api\Entity\AccountBalance;
use Hampel\Saasu\Api\Filter\AccountFilter;
use Hampel\Saasu\Api\Request\AccountRequest;
use Hampel\Saasu\Api\Result\Page;
use Hampel\Saasu\Api\Result\Saved;
use Hampel\Saasu\Api\Support\Cast;

/**
 * The chart of accounts, at `Account` and `Accounts`.
 *
 * Saasu will not delete an account the subscription built in (`IsBuiltIn`), or one with
 * transactions posted to it.
 */
final class Accounts extends Endpoint
{
    /**
     * One page of accounts.
     *
     * @return Page<Account>
     */
    public function list(?AccountFilter $filter = null, int $page = 1, ?int $pageSize = null): Page
    {
        return $this->apiPaginate('Accounts', 'Accounts', Account::fromArray(...), $filter ?? [], $page, $pageSize);
    }

    /**
     * Every matching account, a page at a time, as far as it is consumed. Each page is a request.
     *
     * @return \Generator<int, Account>
     */
    public function each(?AccountFilter $filter = null, ?int $pageSize = null): \Generator
    {
        return $this->apiEach('Accounts', 'Accounts', Account::fromArray(...), $filter ?? [], $pageSize);
    }

    /**
     * Every matching account, as a list - one request per page, so narrow the filter first.
     *
     * @return list<Account>
     */
    public function all(?AccountFilter $filter = null): array
    {
        return iterator_to_array($this->each($filter), false);
    }

    /**
     * One account by id. Raises NotFoundException when there is none.
     */
    public function get(int $id): Account
    {
        return $this->apiObject($this->path($id), Account::fromArray(...));
    }

    /**
     * One account by id, or null.
     */
    public function find(int $id): ?Account
    {
        return $this->apiFind($this->path($id), Account::fromArray(...));
    }

    /**
     * Insert an account. Keep the answer's LastUpdatedId: it is what the next update needs.
     */
    public function create(AccountRequest $request): Saved
    {
        return $this->apiSave('POST', 'Account', $request);
    }

    /**
     * Update an account.
     *
     * Build the request from() the account as read - it carries the LastUpdatedId Saasu requires,
     * and the fields a PUT might otherwise clear. Refused before sending without the
     * LastUpdatedId; raises ConcurrencyException when the account changed after it was read.
     */
    public function update(int $id, AccountRequest $request): Saved
    {
        $this->assertUpdatable($request, 'account');

        return $this->apiSave('PUT', $this->path($id), $request, $id);
    }

    /**
     * Delete an account. Saasu documents a status message in answer rather than a 204; it is dropped.
     */
    public function delete(int $id): void
    {
        $this->apiDelete($this->path($id));
    }

    /**
     * The balance of every bank account.
     *
     * A BANK ACCOUNT WITH NO TRANSACTIONS IS NOT LISTED, rather than listed at zero - measured
     * 2026-09-14. Absent from this list means a balance of nothing, not an unknown account.
     *
     * @return list<AccountBalance>
     */
    public function bankBalances(): array
    {
        return Cast::objects($this->apiGet('Accounts/BankAccountBalances')->value('Balances'), AccountBalance::fromArray(...));
    }

    private function path(int $id): string
    {
        return 'Account/' . Cast::id($id, 'account');
    }
}
