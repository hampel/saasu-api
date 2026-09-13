<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Endpoint;

use Hampel\Saasu\Api\Connection;
use Hampel\Saasu\Api\Exception\InvalidArgumentException;
use Hampel\Saasu\Api\Exception\NotFoundException;
use Hampel\Saasu\Api\Filter\Filter;
use Hampel\Saasu\Api\Request\Payload;
use Hampel\Saasu\Api\Result\ApiResponse;
use Hampel\Saasu\Api\Result\Document;
use Hampel\Saasu\Api\Result\Page;
use Hampel\Saasu\Api\Result\Saved;
use Hampel\Saasu\Api\Support\Cast;

/**
 * The base class for everything that groups a set of Saasu operations - this package's own,
 * and anybody else's.
 *
 *     final class Debtors extends Endpoint
 *     {
 *         public function overdue(): \Generator
 *         {
 *             return $this->apiEach('Invoices', 'Invoices', Invoice::fromArray(...), ['PaymentStatus' => 'U']);
 *         }
 *     }
 *
 *     $saasu->endpoint(Debtors::class)->overdue();
 *
 * There is nothing to register and no container. The class IS the registration, so a
 * third-party package ships one, a consumer type-hints it, and static analysis follows the
 * return type through.
 *
 * Every helper carries an `api` prefix, which looks redundant and is not: an endpoint wants to
 * call its own methods get(), create() and delete(), and PHP will not let a subclass redeclare
 * an inherited method with a different signature.
 */
abstract class Endpoint
{
    public function __construct(
        protected readonly Connection $connection,
    ) {
    }

    /**
     * Refuse a class name that cannot be constructed as an endpoint - for a consumer reading
     * class names out of configuration, where static analysis cannot help.
     */
    public static function assertConstructible(string $class): void
    {
        if (!is_subclass_of($class, self::class) || !(new \ReflectionClass($class))->isInstantiable()) {
            throw new InvalidArgumentException(sprintf(
                '%s cannot be constructed as a Saasu endpoint: it must be a concrete subclass of %s.',
                $class,
                self::class
            ));
        }
    }

    /**
     * @param  array<string, scalar|null>  $query
     */
    protected function apiGet(string $path, array $query = []): ApiResponse
    {
        return $this->connection->get($path, $query);
    }

    /**
     * @param  array<string, mixed>|Payload  $payload
     * @param  array<string, scalar|null>  $query
     */
    protected function apiPost(string $path, array|Payload $payload = [], array $query = []): ApiResponse
    {
        return $this->connection->post($path, $payload instanceof Payload ? $payload->toArray() : $payload, $query);
    }

    /**
     * @param  array<string, mixed>|Payload  $payload
     * @param  array<string, scalar|null>  $query
     */
    protected function apiPut(string $path, array|Payload $payload = [], array $query = []): ApiResponse
    {
        return $this->connection->put($path, $payload instanceof Payload ? $payload->toArray() : $payload, $query);
    }

    /**
     * @param  array<string, scalar|null>  $query
     */
    protected function apiDelete(string $path, array $query = []): ApiResponse
    {
        return $this->connection->delete($path, $query);
    }

    /**
     * @param  array<string, scalar|null>  $query
     */
    protected function apiDocument(string $path, array $query = []): Document
    {
        return $this->connection->document($path, $query);
    }

    /**
     * A read whose answer is one record, at the top level of the body.
     *
     * @template TItem
     * @param  callable(array<string, mixed>): TItem  $map
     * @param  array<string, scalar|null>  $query
     * @return TItem
     */
    protected function apiObject(string $path, callable $map, array $query = []): mixed
    {
        return $map($this->apiGet($path, $query)->object());
    }

    /**
     * A lookup where "no such record" is an ordinary answer.
     *
     * ONLY A 404 FOR A RECORD BECOMES NULL. A 404 whose body is an HTML page is an unrouted
     * path, measured as such, and is raised - a mistyped path in a custom endpoint is not "that
     * record does not exist". A 401 is raised too, for the same reason.
     *
     * @template TItem
     * @param  callable(array<string, mixed>): TItem  $map
     * @param  array<string, scalar|null>  $query
     * @return TItem|null
     */
    protected function apiFind(string $path, callable $map, array $query = []): mixed
    {
        try {
            $object = $this->apiGet($path, $query)->object();
        } catch (NotFoundException $e) {
            if ($e->isUnroutedPath()) {
                throw $e;
            }

            return null;
        }

        return $object === [] ? null : $map($object);
    }

    /**
     * One page of a list.
     *
     * @template TItem
     * @param  string  $key  the key the items are under - `Contacts`, `Invoices`
     * @param  callable(array<string, mixed>): TItem  $map
     * @param  Filter|array<string, scalar|null>  $query
     * @return Page<TItem>
     */
    protected function apiPaginate(
        string $path,
        string $key,
        callable $map,
        Filter|array $query = [],
        int $page = 1,
        ?int $pageSize = null,
    ): Page {
        $pageSize ??= $this->connection->config()->pageSize;
        Page::assertValidPageSize($pageSize);

        if ($page < 1) {
            throw new InvalidArgumentException(sprintf('Saasu numbers pages from 1; page %d was asked for.', $page));
        }

        $query = ($query instanceof Filter ? $query->toQuery() : $query) + ['Page' => $page, 'PageSize' => $pageSize];

        return Page::fromResponse($this->apiGet($path, $query), $key, $map, $page, $pageSize);
    }

    /**
     * Every item across every page, fetched a page at a time and only as far as it is consumed.
     *
     * EVERY PAGE IS A REQUEST AGAINST THE DAILY QUOTA, and Saasu offers no total to say how
     * many pages there will be. Stopping early - a `break` in the foreach - stops the
     * requests. For a walk that must not run away, set Config::$requestBudget.
     *
     * A WALK IS A SAMPLE, NOT A SNAPSHOT: a record created mid-walk may appear twice or not at
     * all. Where completeness matters, de-duplicate by id.
     *
     * @template TItem
     * @param  callable(array<string, mixed>): TItem  $map
     * @param  Filter|array<string, scalar|null>  $query
     * @return \Generator<int, TItem>
     */
    protected function apiEach(string $path, string $key, callable $map, Filter|array $query = [], ?int $pageSize = null): \Generator
    {
        $page = 1;

        do {
            $result = $this->apiPaginate($path, $key, $map, $query, $page++, $pageSize);

            yield from $result->items;
        } while ($result->hasMore());
    }

    /**
     * An insert or update, answered with the id and the new LastUpdatedId.
     *
     * @param  array<string, mixed>|Payload  $payload
     */
    protected function apiSave(string $method, string $path, array|Payload $payload, ?int $id = null): Saved
    {
        $response = $method === 'PUT' ? $this->apiPut($path, $payload) : $this->apiPost($path, $payload);

        return Saved::fromResponse($response, $id);
    }

    /**
     * Refuse an update that cannot succeed, before it spends a request.
     *
     * Saasu requires the `LastUpdatedId` the record had when it was read, and answers without
     * one by refusing the write. The request builders' from() carries it over from the record.
     */
    protected function assertUpdatable(Payload $payload, string $what, string $field = 'LastUpdatedId'): void
    {
        if (Cast::text($payload->get($field)) === null) {
            throw new InvalidArgumentException(sprintf(
                'Updating a %s needs the %s it had when it was read, and this request has none. Build '
                    . 'it with %s::from() on the record being updated, which carries it over.',
                $what,
                $field,
                $payload::class
            ));
        }
    }
}
