<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Tests;

use Hampel\Saasu\Api\Authentication\WsAccessKey;
use Hampel\Saasu\Api\Config;
use Hampel\Saasu\Api\Exception\ClientException;
use Hampel\Saasu\Api\Exception\ConcurrencyException;
use Hampel\Saasu\Api\Exception\InvalidArgumentException;
use Hampel\Saasu\Api\Exception\MalformedResponseException;
use Hampel\Saasu\Api\Exception\NotAuthenticatedException;
use Hampel\Saasu\Api\Exception\NotFoundException;
use Hampel\Saasu\Api\Exception\NotPermittedException;
use Hampel\Saasu\Api\Exception\RequestBudgetExhaustedException;
use Hampel\Saasu\Api\Exception\RequestException;
use Hampel\Saasu\Api\Exception\RuntimeException;
use Hampel\Saasu\Api\Exception\ServerException;
use Hampel\Saasu\Api\Exception\TooManyRequestsException;
use Hampel\Saasu\Api\Exception\TwoFactorRequiredException;
use Hampel\Saasu\Api\Exception\ValidationException;
use Hampel\Saasu\Api\Throttle\Throttle;
use PHPUnit\Framework\Attributes\DataProvider;

final class ConnectionTest extends TestCase
{
    public function testEveryFileScopedRequestNamesTheFile(): void
    {
        $this->pushFixture('contacts');

        $this->saasu()->connection()->get('Contacts', ['IsCustomer' => true]);

        $this->assertSame(['FileId' => '123', 'IsCustomer' => 'true'], $this->sentQuery());
        $this->assertSame('/Contacts', $this->sentPath());
    }

    public function testItPinsTheApiVersionAndAsksForJson(): void
    {
        $this->pushFixture('contacts');

        $this->saasu()->connection()->get('Contacts');

        $request = $this->client->lastRequest();

        $this->assertSame('1.0', $request->getHeaderLine('X-Api-Version'));
        $this->assertSame('application/json', $request->getHeaderLine('Accept'));
        $this->assertSame('Bearer test-access-token', $request->getHeaderLine('Authorization'));
    }

    public function testANullApiVersionSendsNoHeader(): void
    {
        $this->pushFixture('contacts');

        $this->saasu(new Config(self::FILE_ID, apiVersion: null))->connection()->get('Contacts');

        $this->assertFalse($this->client->lastRequest()->hasHeader('X-Api-Version'));
    }

    /**
     * A request that needs a file on a client with none is refused before it spends a request -
     * sent without one, Saasu would answer for a file nobody chose, or refuse.
     */
    public function testAFileScopedRequestWithNoFileIdIsRefusedBeforeSending(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('without a file id');

        try {
            $this->saasu(new Config())->connection()->get('Contacts');
        } finally {
            $this->assertSame([], $this->client->requests);
        }
    }

    public function testAnUnscopedConnectionSendsNoFileId(): void
    {
        $this->pushFixture('user');

        $this->saasu()->connection()->withoutFile()->get('User');

        $this->assertSame([], $this->sentQuery());
    }

    public function testAFileIdAlreadyInTheUriIsNotDoubled(): void
    {
        $this->pushFixture('contact');

        $this->saasu()->connection()->follow('https://api.saasu.com/Contact/54353?FileId=123');

        $this->assertSame('FileId=123', $this->client->lastRequest()->getUri()->getQuery());
    }

    public function testItPostsJson(): void
    {
        $this->pushFixture('contact-inserted');

        $this->saasu()->connection()->post('Contact', ['GivenName' => 'Joe', 'DefaultSaleDiscount' => 10.0]);

        $this->assertSame('POST', $this->sentMethod());
        $this->assertSame('application/json', $this->client->lastRequest()->getHeaderLine('Content-Type'));
        $this->assertSame('{"GivenName":"Joe","DefaultSaleDiscount":10.0}', (string) $this->client->lastRequest()->getBody());
    }

    /**
     * `authorisation/ping` answers with a bare `true`, which is JSON and not an object.
     */
    public function testABareJsonScalarIsASuccess(): void
    {
        $this->client->pushRaw(200, 'true', ['Content-Type' => 'application/json']);

        $response = $this->saasu()->connection()->get('authorisation/ping');

        $this->assertTrue($response->body);
        $this->assertSame([], $response->data);
    }

    public function testA204IsAnEmptySuccess(): void
    {
        $this->client->pushRaw(204, '');

        $this->assertTrue($this->saasu()->connection()->delete('Contact/1')->isEmpty());
    }

    /**
     * An empty GET read as an empty list would reach the caller as "no unpaid invoices".
     */
    public function testAnEmptyBodyOnAGetIsMalformedRatherThanEmpty(): void
    {
        $this->client->pushRaw(200, '');

        $this->expectException(MalformedResponseException::class);

        $this->saasu()->connection()->get('Invoices');
    }

    public function testAnHtmlSuccessIsMalformed(): void
    {
        $this->client->pushRaw(200, '<!DOCTYPE html><html><body>Checking your browser</body></html>', ['Content-Type' => 'text/html']);

        $this->expectException(MalformedResponseException::class);
        $this->expectExceptionMessage('not JSON');

        $this->saasu()->connection()->get('Invoices');
    }

    /**
     * @param  class-string<\Throwable>  $expected
     */
    #[DataProvider('statuses')]
    public function testItMapsAStatusToItsExceptionType(int $status, string $body, string $expected): void
    {
        $this->client->pushRaw($status, $body, ['Content-Type' => 'application/json']);

        $this->expectException($expected);

        $this->saasu()->connection()->get('Contact/1');
    }

    /**
     * @return iterable<string, array{int, string, class-string<\Throwable>}>
     */
    public static function statuses(): iterable
    {
        yield '400 sentence' => [400, '"Please specify AdjustmentItems for this transaction."', ValidationException::class];
        yield '400 stale' => [400, '"Record to be updated has changed since last read."', ConcurrencyException::class];
        yield '401' => [401, '', NotAuthenticatedException::class];
        yield '401 2fa' => [401, '{"error":"2fa_code_required","is_2fa_code_required":true,"is_2fa_code_sent":true}', TwoFactorRequiredException::class];
        yield '403' => [403, '', NotPermittedException::class];
        yield '403 sentence' => [403, '"Not authorised"', NotPermittedException::class];
        yield '400 permission' => [400, '{"Message":"Sorry, you do not have permission to access this feature. Please contact your membership administrator."}', NotPermittedException::class];
        yield '404' => [404, '"The requested transaction is not found."', NotFoundException::class];
        yield '429' => [429, '', TooManyRequestsException::class];
        yield '500' => [500, '{"Message":"An error has occurred."}', ServerException::class];
        yield '409' => [409, '', ClientException::class];
        yield '409 stale' => [409, '"Record to be updated has changed since last read."', ConcurrencyException::class];
    }

    /**
     * The shape Saasu's own .NET client tests assert: a bare JSON string.
     */
    public function testAStaleUpdateIsReportedAsAConcurrencyConflictNotAValidationFailure(): void
    {
        $this->client->pushRaw(400, '"Record to be updated has changed since last read."');

        try {
            $this->saasu()->connection()->put('Contact/1', ['LastUpdatedId' => 'old']);

            $this->fail('expected a ConcurrencyException');
        } catch (ConcurrencyException $e) {
            $this->assertNotContains(ValidationException::class, class_parents($e));
            $this->assertSame(['Record to be updated has changed since last read.'], $e->messages());
            $this->assertStringContainsString('changed since last read', $e->getMessage());
        }
    }

    /**
     * .NET exception text carries CRLFs and internal property names; the message is flattened to
     * one line, and mentions() is the way to branch on prose.
     */
    public function testAValidationSentenceIsFlattenedAndSearchable(): void
    {
        $this->client->pushRaw(400, json_encode("The Item is marked as 'Sold' and therefore needs to have an Income Account assigned to it.\r\nParameter name: SaleIncomeAccountUid\r\n", JSON_THROW_ON_ERROR));

        try {
            $this->saasu()->connection()->post('Item', []);

            $this->fail('expected a ValidationException');
        } catch (ValidationException $e) {
            $this->assertTrue($e->mentions('Income Account'));
            $this->assertStringNotContainsString("\n", $e->getMessage());
            $this->assertStringContainsString('assigned to it. Parameter name: SaleIncomeAccountUid', $e->getMessage());
        }
    }

    public function testAModelStateIsReadAsFieldErrorsWithoutTheParameterPrefix(): void
    {
        $this->client->pushJson(400, [
            'Message' => 'The request is invalid.',
            'ModelState' => ['contact.EmailAddress' => ['The EmailAddress field is not a valid e-mail address.']],
        ]);

        try {
            $this->saasu()->connection()->post('Contact', []);

            $this->fail('expected a ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(['EmailAddress' => ['The EmailAddress field is not a valid e-mail address.']], $e->fieldErrors());
            $this->assertStringContainsString('EmailAddress: The EmailAddress field', $e->getMessage());
        }
    }

    /**
     * Measured 2026-09-13: a 401 from a data endpoint has no body, so the message is written.
     */
    public function testABodiless401ExplainsItself(): void
    {
        $this->client->pushRaw(401, '');

        $this->expectException(NotAuthenticatedException::class);
        $this->expectExceptionMessage('carries no body');

        $this->saasu()->connection()->get('Contacts');
    }

    /**
     * Measured 2026-09-13: an unrouted path answers 404 with an IIS HTML page.
     */
    public function testAnHtml404IsAnUnroutedPath(): void
    {
        $this->client->pushRaw(404, '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"><html><title>404 - File or directory not found.</title></html>', ['Content-Type' => 'text/html']);

        try {
            $this->saasu()->connection()->get('NoSuchThing');

            $this->fail('expected a NotFoundException');
        } catch (NotFoundException $e) {
            $this->assertTrue($e->isUnroutedPath());
            $this->assertStringContainsString('unrouted path', $e->getMessage());
        }
    }

    /**
     * The legacy key is a query parameter, so it is in the URI - and URIs go into messages and
     * logs. Neither may carry it.
     */
    public function testTheAccessKeyIsRedactedFromExceptionsAndLogs(): void
    {
        $logger = new RecordingLogger();
        $this->client->pushRaw(500, '{"Message":"An error has occurred."}');

        $saasu = $this->saasu(authentication: new WsAccessKey('SECRETKEY1234567890ABCDEF'), logger: $logger);

        try {
            $saasu->connection()->get('Contacts');

            $this->fail('expected a ServerException');
        } catch (ServerException $e) {
            $this->assertStringNotContainsString('SECRETKEY', $e->getMessage());
            $this->assertStringContainsString('wsAccessKey=[redacted]', $e->getMessage());
        }

        $this->assertStringContainsString('SECRETKEY1234567890ABCDEF', (string) $this->client->lastRequest()->getUri());
        $this->assertStringNotContainsString('SECRETKEY', (string) json_encode($logger->records));
    }

    public function testATransportFailureIsARequestExceptionWithTheKeyRedacted(): void
    {
        $saasu = $this->saasu(authentication: new WsAccessKey('SECRETKEY1234567890ABCDEF'));
        $request = $saasu->connection()->request('GET', 'Contacts');
        $this->client->push(new TransportFailure($request, 'cURL error 28 for https://api.saasu.com/Contacts?FileId=123&wsAccessKey=SECRETKEY1234567890ABCDEF'));

        try {
            $saasu->connection()->get('Contacts');

            $this->fail('expected a RequestException');
        } catch (RequestException $e) {
            $this->assertStringNotContainsString('SECRETKEY', $e->getMessage());
        }
    }

    /**
     * Laravel's StrayRequestException is a plain RuntimeException, and must reach the consumer's
     * test as itself rather than as "could not reach Saasu".
     */
    public function testSomethingThatIsNotAPsr18FailurePassesThroughUntouched(): void
    {
        $stray = new \RuntimeException('Attempted request to [https://api.saasu.com/Contacts] without a matching fake.');
        $this->client->pushThrowable($stray);

        try {
            $this->saasu()->connection()->get('Contacts');

            $this->fail('expected the stray exception');
        } catch (\RuntimeException $e) {
            $this->assertSame($stray, $e);
        }
    }

    /**
     * Saasu blocks a file's API for 24 hours once its daily quota is gone; the budget stops a
     * runaway walk before it gets there.
     */
    public function testTheRequestBudgetRefusesTheRequestThatWouldExceedIt(): void
    {
        $this->pushFixture('contact');
        $this->pushFixture('contact');

        $saasu = $this->saasu(new Config(self::FILE_ID, requestBudget: 2));
        $saasu->contacts()->get(1);
        $saasu->contacts()->get(2);

        $this->expectException(RequestBudgetExhaustedException::class);

        try {
            $saasu->contacts()->get(3);
        } finally {
            $this->assertCount(2, $this->client->requests);
            $this->assertSame(2, $saasu->requestsSent());
        }
    }

    /**
     * The throttle is told which file each request is for, so one keyed by file can follow a
     * client derived for another file - and a request naming no file says so.
     */
    public function testTheThrottleIsToldTheFileOfEveryRequest(): void
    {
        $throttle = new class () implements Throttle {
            /** @var list<int|null> */
            public array $files = [];

            public function wait(?int $fileId): void
            {
                $this->files[] = $fileId;
            }
        };

        $this->pushFixture('contact');
        $this->pushFixture('contact');
        $this->client->pushJson(200, ['FileIdentities' => []]);
        $this->pushFixture('contact');

        $saasu = $this->saasu(throttle: $throttle);
        $saasu->contacts()->get(1);
        $saasu->withFileId(456)->contacts()->get(2);
        $saasu->files()->all();
        $saasu->connection()->get('Contact/3', ['fileid' => '789']);

        $this->assertSame([123, 456, null, 789], $throttle->files);
    }

    /**
     * A `_links` href is requested with the credential attached, so one naming another host is
     * refused rather than followed.
     */
    public function testFollowingALinkToAnotherHostIsRefused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Refusing to follow');

        $this->saasu(authentication: new WsAccessKey('SECRETKEY1234567890ABCDEF'))
            ->connection()
            ->follow('https://attacker.example/steal?FileId=123');
    }

    public function testADocumentKeepsItsBytesAndReadsTheFilename(): void
    {
        $this->client->pushRaw(200, "%PDF-1.4\n...", [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="INV-1123.pdf"',
        ]);

        $document = $this->saasu()->connection()->document('Invoice/5093684/generate-pdf');

        $this->assertTrue($document->isPdf());
        $this->assertSame('INV-1123.pdf', $document->filename);
        $this->assertSame('application/pdf', $document->contentType);
    }

    public function testADocumentRequestThatFailsRaises(): void
    {
        $this->client->pushRaw(404, '"The requested transaction is not found."');

        $this->expectException(NotFoundException::class);

        $this->saasu()->connection()->document('Invoice/1/generate-pdf');
    }
}
