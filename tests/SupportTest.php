<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Tests;

use Hampel\Saasu\Api\Config;
use Hampel\Saasu\Api\Enum\AttachedToType;
use Hampel\Saasu\Api\Enum\TagSelection;
use Hampel\Saasu\Api\ErrorDetail;
use Hampel\Saasu\Api\Exception\InvalidArgumentException;
use Hampel\Saasu\Api\Filter\ContactFilter;
use Hampel\Saasu\Api\Filter\DateRange;
use Hampel\Saasu\Api\Filter\PaymentFilter;
use Hampel\Saasu\Api\Result\Document;
use Hampel\Saasu\Api\Support\Cast;
use Hampel\Saasu\Api\Support\Redact;
use Hampel\Saasu\Api\Throttle\IntervalThrottle;
use PHPUnit\Framework\TestCase as BaseTestCase;

final class SupportTest extends BaseTestCase
{
    /**
     * An invoice dated the 24th is dated the 24th wherever it is read - including when Saasu
     * attaches an offset, and when PHP's own default timezone is ten hours from UTC.
     */
    public function testACalendarDateIsNeverConvertedBetweenTimezones(): void
    {
        $default = date_default_timezone_get();
        date_default_timezone_set('Australia/Sydney');

        try {
            $this->assertSame('2015-01-24', Cast::date('2015-01-24T00:00:00')?->format('Y-m-d'));
            $this->assertSame('2026-09-13', Cast::date('2026-09-13T00:00:00+10:00')?->format('Y-m-d'));
            $this->assertSame('2026-09-13', Cast::date('2026-09-13')?->format('Y-m-d'));
            $this->assertNull(Cast::date('not a date'));
        } finally {
            date_default_timezone_set($default);
        }
    }

    public function testAnInstantIsConvertedToUtcAndABareOneIsReadAsUtc(): void
    {
        $default = date_default_timezone_get();
        date_default_timezone_set('Australia/Sydney');

        try {
            $this->assertSame('2026-09-12 19:00:11', Cast::datetime('2026-09-12T19:00:11.9684645Z')?->format('Y-m-d H:i:s'));
            $this->assertSame('2026-09-12 19:00:12', Cast::datetime('2026-09-13T05:00:12+10:00')?->format('Y-m-d H:i:s'));
            $this->assertSame('2026-09-12 19:00:00', Cast::datetime('2026-09-12T19:00:00')?->format('Y-m-d H:i:s'));
        } finally {
            date_default_timezone_set($default);
        }
    }

    /**
     * A shift that started at 9am started at 9am.
     */
    public function testALocalTimeKeepsItsWallClock(): void
    {
        $this->assertSame('09:23', Cast::localDateTime('2026-09-13T09:23:31.5498262+10:00')?->format('H:i'));
        $this->assertSame('09:23', Cast::localDateTime('2026-09-13T09:23:31')?->format('H:i'));
        $this->assertSame('2026-09-13T09:00:00', Cast::localDateTimeString(new \DateTimeImmutable('2026-09-13 09:00', new \DateTimeZone('Australia/Perth'))));
    }

    public function testADateGoingOutIsItsOwnWallClockDate(): void
    {
        $sydneyMidnight = new \DateTimeImmutable('2026-07-01 00:00', new \DateTimeZone('Australia/Sydney'));

        $this->assertSame('2026-07-01', Cast::dateString($sydneyMidnight));
        $this->assertSame('2026-07-01', Cast::dateString('2026-07-01T00:00:00'));
    }

    public function testAnEnumIsMatchedWhateverTheCase(): void
    {
        $this->assertSame(AttachedToType::Sale, Cast::enum(AttachedToType::class, 'sale'));
        $this->assertSame(AttachedToType::Sale, Cast::enum(AttachedToType::class, 'Sale'));
        $this->assertNull(Cast::enum(AttachedToType::class, 'Invoice'));
    }

    /**
     * Records carry tags as a list; search results carry them as one comma-separated string.
     */
    public function testTagsAreReadFromEitherShape(): void
    {
        $this->assertSame(['Advisor', 'Business'], Cast::tags(['Advisor', 'Business']));
        $this->assertSame(['Advisor', 'Business'], Cast::tags('Advisor, Business,'));
        $this->assertSame([], Cast::tags(''));
    }

    public function testTheEmptyStringIsAbsentText(): void
    {
        $this->assertNull(Cast::text(''));
        $this->assertNull(Cast::text(null));
        $this->assertSame('x', Cast::text('x'));
    }

    public function testRedactionMasksTheKeyInAnyCase(): void
    {
        $this->assertSame(
            'https://api.saasu.com/FileIdentity?wsaccesskey=[redacted]&fileid=1',
            Redact::uri('https://api.saasu.com/FileIdentity?wsaccesskey=869af5a2144523079a8c&fileid=1')
        );
        $this->assertSame('https://api.saasu.com/Contacts?FileId=1&wsAccessKey=[redacted]', Redact::uri('https://api.saasu.com/Contacts?FileId=1&wsAccessKey=ABC'));
    }

    public function testTheThrottleWaitsOutTheRestOfTheInterval(): void
    {
        $now = 100.0;
        $slept = [];
        $throttle = new IntervalThrottle(1.0, function (float $seconds) use (&$slept, &$now): void {
            $slept[] = round($seconds, 3);
            $now += $seconds;
        }, function () use (&$now): float {
            return $now;
        });

        $throttle->wait(null);
        $now += 0.25;
        $throttle->wait(null);
        $now += 5.0;
        $throttle->wait(null);

        $this->assertSame([0.75], $slept);
    }

    public function testConfigRefusesWhatSaasuWouldRefuse(): void
    {
        foreach ([
            static fn () => new Config(0),
            static fn () => new Config(1, pageSize: 101),
            static fn () => new Config(1, pageSize: 0),
            static fn () => new Config(1, apiVersion: 'v1'),
            static fn () => new Config(1, requestBudget: 0),
            static fn () => new Config(1, baseUri: 'api.saasu.com'),
        ] as $build) {
            try {
                $build();
                $this->fail('expected an InvalidArgumentException');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testConfigDefaultsToTheLargestPage(): void
    {
        $this->assertSame(100, (new Config(1))->pageSize);
        $this->assertSame(456, (new Config(1))->withFileId(456)->fileId);
    }

    public function testConfigOwnsOnlyItsOwnHost(): void
    {
        $config = new Config(1);

        $this->assertTrue($config->ownsUri('https://api.saasu.com/Contacts?FileId=1'));
        $this->assertTrue($config->ownsUri('https://API.SAASU.COM/Contacts'));
        $this->assertFalse($config->ownsUri('http://api.saasu.com/Contacts'));
        $this->assertFalse($config->ownsUri('https://api.saasu.com.attacker.example/Contacts'));
    }

    /**
     * Half a date range is not a narrower query to Saasu, so it cannot be built.
     */
    public function testADateRangeHasBothEndsInOrder(): void
    {
        $this->assertSame(['FromDate' => '2026-07-01', 'ToDate' => '2027-06-30'], DateRange::financialYear(2027)->toQuery('FromDate', 'ToDate'));

        $this->expectException(InvalidArgumentException::class);

        DateRange::between('2026-09-13', '2026-09-01');
    }

    public function testAFilterIsImmutableAndSendsSaasusNames(): void
    {
        $base = ContactFilter::all()->customers();
        $narrowed = $base->tagged(['VIP', 'Wholesale'], TagSelection::RequireAll)->modifiedBetween(DateRange::on('2026-09-13'));

        $this->assertSame(['IsCustomer' => true], $base->toQuery());
        $this->assertSame([
            'IsCustomer' => true,
            'Tags' => 'VIP,Wholesale',
            'TagSelection' => 'requireAll',
            'LastModifiedFromDate' => '2026-09-13',
            'LastModifiedToDate' => '2026-09-13',
        ], $narrowed->toQuery());
    }

    public function testATagCannotContainTheSeparator(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ContactFilter::all()->tagged(['a,b']);
    }

    public function testAPaymentFilterSendsThePaymentDateRange(): void
    {
        $this->assertSame(
            ['PaymentFromDate' => '2025-07-01', 'PaymentToDate' => '2026-06-30'],
            PaymentFilter::all()->paidBetween(DateRange::financialYear(2026))->toQuery()
        );
    }

    /**
     * @return iterable<string, array{string, list<string>, string|null}>
     */
    public static function errorBodies(): iterable
    {
        yield 'bare string' => ['"Record to be updated has changed since last read."', ['Record to be updated has changed since last read.'], null];
        yield 'web api object' => ['{"Message":"The request is invalid.","ExceptionMessage":"Boom"}', ['The request is invalid.', 'Boom'], null];
        yield 'oauth' => ['{"error":"invalid_credentials","error_description":"Invalid username or password","data":null,"is_2fa_code_required":false,"is_2fa_code_sent":false}', ['Invalid username or password'], 'invalid_credentials'];
        yield 'list' => ['["one", {"Message": "two"}]', ['one', 'two'], null];
    }

    /**
     * @param  list<string>  $messages
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('errorBodies')]
    public function testEveryErrorShapeIsRead(string $body, array $messages, ?string $oauthError): void
    {
        $error = ErrorDetail::fromBody($body);

        $this->assertNotNull($error);
        $this->assertSame($messages, $error->messages());
        $this->assertSame($oauthError, $error->oauthError);
    }

    public function testNothingToReadIsNull(): void
    {
        $this->assertNull(ErrorDetail::fromBody(''));
        $this->assertNull(ErrorDetail::fromBody('<!DOCTYPE html><html></html>'));
        $this->assertNull(ErrorDetail::fromBody('{}'));
        $this->assertTrue(ErrorDetail::looksLikeHtml("  <!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Strict//EN\">"));
    }

    public function testADocumentFilenameIsReadFromEitherDispositionForm(): void
    {
        $this->assertSame('INV 1.pdf', Document::filenameFrom("attachment; filename*=UTF-8''INV%201.pdf"));
        $this->assertSame('payslip.pdf', Document::filenameFrom('inline; filename="../../payslip.pdf"'));
        $this->assertNull(Document::filenameFrom('inline'));
    }
}
