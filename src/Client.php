<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api;

use Hampel\Saasu\Api\Authentication\Authentication;
use Hampel\Saasu\Api\Authentication\OAuth;
use Hampel\Saasu\Api\Authentication\TokenStore;
use Hampel\Saasu\Api\Authentication\WsAccessKey;
use Hampel\Saasu\Api\Endpoint\Accounts;
use Hampel\Saasu\Api\Endpoint\Activities;
use Hampel\Saasu\Api\Endpoint\Attachments;
use Hampel\Saasu\Api\Endpoint\Authorisation;
use Hampel\Saasu\Api\Endpoint\Brands;
use Hampel\Saasu\Api\Endpoint\Companies;
use Hampel\Saasu\Api\Endpoint\ContactAggregates;
use Hampel\Saasu\Api\Endpoint\Contacts;
use Hampel\Saasu\Api\Endpoint\DeletedEntities;
use Hampel\Saasu\Api\Endpoint\Employees;
use Hampel\Saasu\Api\Endpoint\Endpoint;
use Hampel\Saasu\Api\Endpoint\Files;
use Hampel\Saasu\Api\Endpoint\Invoices;
use Hampel\Saasu\Api\Endpoint\ItemAdjustments;
use Hampel\Saasu\Api\Endpoint\Items;
use Hampel\Saasu\Api\Endpoint\ItemTransfers;
use Hampel\Saasu\Api\Endpoint\Journals;
use Hampel\Saasu\Api\Endpoint\LeaveRequests;
use Hampel\Saasu\Api\Endpoint\Lookups;
use Hampel\Saasu\Api\Endpoint\Payments;
use Hampel\Saasu\Api\Endpoint\PayrollEntries;
use Hampel\Saasu\Api\Endpoint\Reports;
use Hampel\Saasu\Api\Endpoint\Search;
use Hampel\Saasu\Api\Endpoint\TaxCodes;
use Hampel\Saasu\Api\Endpoint\Timesheets;
use Hampel\Saasu\Api\Endpoint\Users;
use Hampel\Saasu\Api\Entity\FileIdentity;
use Hampel\Saasu\Api\Support\Psr17Discovery;
use Hampel\Saasu\Api\Support\RequestCounter;
use Hampel\Saasu\Api\Throttle\IntervalThrottle;
use Hampel\Saasu\Api\Throttle\Throttle;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * The entry point.
 *
 *     $saasu = Client::withOAuth('api@example.com', $password, fileId: 12345, client: new GuzzleHttp\Client());
 *
 *     $saasu->verify();                                  // does this credential reach this file?
 *     $saasu->invoices()->list(InvoiceFilter::sales()->unpaid());
 *
 * THE API IT WRAPS IS RATIONED, and that shapes everything here. Saasu allows one request a
 * second and a daily quota of a few thousand, and blocks a file's API access for 24 hours from
 * the moment the quota is exceeded - for every integration on that file, not just the one that
 * spent it. So by default the client waits out the one-second limit itself (Throttle), a token is
 * reused rather than re-requested (TokenStore), lists ask for the largest page Saasu allows
 * (Config::$pageSize), and a caller can cap what one client may spend (Config::$requestBudget).
 *
 * A client derived with withFileId(), withConfig() or withCredential() shares its parent's
 * throttle and request count, because the limit they enforce belongs to Saasu, not to an object.
 */
final class Client
{
    private readonly Connection $connection;

    /** @var array<class-string<Endpoint>, Endpoint> */
    private array $endpoints = [];

    /**
     * @param  RequestFactoryInterface|null  $requestFactory  PSR-17. Leave both null and the
     *         package finds one - Guzzle's, Nyholm's or Diactoros' - see Psr17Discovery
     * @param  Throttle|null  $throttle  null enforces Saasu's one request a second in this process
     */
    public function __construct(
        private readonly Config $config,
        private readonly Authentication $authentication,
        ClientInterface $client,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        private readonly LoggerInterface $logger = new NullLogger(),
        ?Throttle $throttle = null,
        ?RequestCounter $counter = null,
    ) {
        if ($requestFactory === null || $streamFactory === null) {
            [$foundRequest, $foundStream] = Psr17Discovery::find();

            $requestFactory ??= $foundRequest;
            $streamFactory ??= $foundStream;
        }

        $this->connection = new Connection(
            $this->config,
            $this->authentication,
            $client,
            $requestFactory,
            $streamFactory,
            $throttle ?? new IntervalThrottle(),
            $counter ?? new RequestCounter(),
            $this->logger,
        );
    }

    /**
     * Saasu's preferred credential: a username and password, exchanged for a token that renews
     * itself. Pass a TokenStore for anything longer-lived than one process - see TokenStore.
     */
    public static function withOAuth(
        string $username,
        #[\SensitiveParameter] string $password,
        ?int $fileId,
        ClientInterface $client,
        ?TokenStore $store = null,
        ?LoggerInterface $logger = null,
    ): self {
        return new self(
            new Config($fileId),
            OAuth::password($username, $password, $store),
            $client,
            logger: $logger ?? new NullLogger(),
        );
    }

    /**
     * The legacy web services access key, from Saasu's Settings - Web Services page. It travels
     * in the URL - see WsAccessKey.
     */
    public static function withAccessKey(
        #[\SensitiveParameter] string $key,
        int $fileId,
        ClientInterface $client,
        ?LoggerInterface $logger = null,
    ): self {
        return new self(new Config($fileId), new WsAccessKey($key), $client, logger: $logger ?? new NullLogger());
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function authentication(): Authentication
    {
        return $this->authentication;
    }

    /**
     * Does this credential reach this file - in one request that changes nothing.
     *
     * Answers with the file's identity, whose name is worth checking: a credential that reaches
     * several files will verify happily against the wrong one.
     */
    public function verify(): FileIdentity
    {
        return $this->files()->identity();
    }

    /**
     * How many requests this client, and every client derived from it, has sent.
     */
    public function requestsSent(): int
    {
        return $this->connection->requestsSent();
    }

    /**
     * The same credential, transport and throttle, in another file.
     */
    public function withFileId(int $fileId): self
    {
        return $this->withConfig($this->config->withFileId($fileId));
    }

    /**
     * The same credential, transport and throttle, under a different configuration.
     */
    public function withConfig(Config $config): self
    {
        return $this->derive($config, $this->authentication);
    }

    /**
     * The same file, transport and throttle, under a different credential.
     *
     * A new client rather than a mutation, so two credentials in one long-running process cannot
     * leak into each other's requests.
     */
    public function withCredential(Authentication $authentication): self
    {
        return $this->derive($this->config, $authentication);
    }

    /**
     * Any Endpoint subclass, constructed and memoised - the extension point.
     *
     * @template T of Endpoint
     * @param  class-string<T>  $class
     * @return T
     */
    public function endpoint(string $class): Endpoint
    {
        if (!isset($this->endpoints[$class])) {
            Endpoint::assertConstructible($class);

            $this->endpoints[$class] = new $class($this->connection);
        }

        /** @var T $endpoint */
        $endpoint = $this->endpoints[$class];

        return $endpoint;
    }

    /**
     * The chart of accounts, and bank balances.
     */
    public function accounts(): Accounts
    {
        return $this->endpoint(Accounts::class);
    }

    public function activities(): Activities
    {
        return $this->endpoint(Activities::class);
    }

    /**
     * Invoice attachments, contents and all.
     */
    public function attachments(): Attachments
    {
        return $this->endpoint(Attachments::class);
    }

    /**
     * OAuth tokens, obtained and tested directly. Authentication\OAuth uses this itself.
     */
    public function authorisation(): Authorisation
    {
        return $this->endpoint(Authorisation::class);
    }

    public function brands(): Brands
    {
        return $this->endpoint(Brands::class);
    }

    public function companies(): Companies
    {
        return $this->endpoint(Companies::class);
    }

    public function contacts(): Contacts
    {
        return $this->endpoint(Contacts::class);
    }

    /**
     * A contact with its company and manager, read and written as one.
     */
    public function contactAggregates(): ContactAggregates
    {
        return $this->endpoint(ContactAggregates::class);
    }

    /**
     * Records that were deleted - the other half of a sync.
     */
    public function deletedEntities(): DeletedEntities
    {
        return $this->endpoint(DeletedEntities::class);
    }

    /**
     * Payroll employees and leave entitlements.
     */
    public function employees(): Employees
    {
        return $this->endpoint(Employees::class);
    }

    /**
     * The configured file's business identity, and every file the login can reach.
     */
    public function files(): Files
    {
        return $this->endpoint(Files::class);
    }

    /**
     * Sales and purchases - quotes, orders, tax invoices.
     */
    public function invoices(): Invoices
    {
        return $this->endpoint(Invoices::class);
    }

    public function items(): Items
    {
        return $this->endpoint(Items::class);
    }

    public function itemAdjustments(): ItemAdjustments
    {
        return $this->endpoint(ItemAdjustments::class);
    }

    public function itemTransfers(): ItemTransfers
    {
        return $this->endpoint(ItemTransfers::class);
    }

    public function journals(): Journals
    {
        return $this->endpoint(Journals::class);
    }

    public function leaveRequests(): LeaveRequests
    {
        return $this->endpoint(LeaveRequests::class);
    }

    /**
     * Countries, currencies, tax zones and industry types.
     */
    public function lookups(): Lookups
    {
        return $this->endpoint(Lookups::class);
    }

    public function payments(): Payments
    {
        return $this->endpoint(Payments::class);
    }

    /**
     * Pay run entries and payslips.
     */
    public function payrollEntries(): PayrollEntries
    {
        return $this->endpoint(PayrollEntries::class);
    }

    /**
     * Profit and loss.
     */
    public function reports(): Reports
    {
        return $this->endpoint(Reports::class);
    }

    public function search(): Search
    {
        return $this->endpoint(Search::class);
    }

    public function taxCodes(): TaxCodes
    {
        return $this->endpoint(TaxCodes::class);
    }

    public function timesheets(): Timesheets
    {
        return $this->endpoint(Timesheets::class);
    }

    /**
     * The login the credential belongs to.
     */
    public function user(): Users
    {
        return $this->endpoint(Users::class);
    }

    /**
     * For anything the endpoints do not cover.
     */
    public function connection(): Connection
    {
        return $this->connection;
    }

    private function derive(Config $config, Authentication $authentication): self
    {
        return new self(
            $config,
            $authentication,
            $this->connection->client(),
            $this->connection->requestFactory(),
            $this->connection->streamFactory(),
            $this->logger,
            $this->connection->throttle(),
            $this->connection->counter(),
        );
    }
}
