<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Support;

use Hampel\Saasu\Api\Exception\InvalidArgumentException;

/**
 * Reading a value out of an API result without trusting it.
 *
 * Every one of these returns null - or an empty collection - rather than throwing when the
 * value is absent or the wrong shape. Saasu's documentation is generated from its .NET models
 * and nearly every property is nullable there, including ones that read as mandatory: a
 * contact with no company, an invoice with no due date, an item with no supplier. A client
 * that treated any of those as an error would break on the ordinary case.
 */
final class Cast
{
    public static function string(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * A string that means something, with the empty string read as absent.
     *
     * Saasu writes an unset text field both ways - `"CustomField1": ""` and
     * `"MiddleInitials": null` sit next to each other in the same contact - so a caller asking
     * "is there a value" should not have to ask twice.
     */
    public static function text(mixed $value): ?string
    {
        $string = self::string($value);

        return $string === null || trim($string) === '' ? null : $string;
    }

    public static function int(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        return is_string($value) && $value !== '' && ctype_digit(ltrim($value, '-')) ? (int) $value : null;
    }

    /**
     * A monetary amount, a quantity or a rate.
     *
     * A FLOAT, BECAUSE THAT IS WHAT THE WIRE CARRIED. Saasu sends amounts as JSON numbers, and
     * json_decode() has already made a float of one before anything here sees it - there is no
     * exactness left to preserve by returning a string. Arithmetic on money belongs in a
     * decimal library on the caller's side. Going back out, json_encode() writes the shortest
     * representation that round-trips, so `10.45` is sent as `10.45`.
     */
    public static function float(mixed $value): ?float
    {
        return is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))
            ? (float) $value
            : null;
    }

    public static function bool(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return match ($value) {
            1, '1', 'true', 'True' => true,
            0, '0', 'false', 'False' => false,
            default => null,
        };
    }

    /**
     * @return array<mixed>
     */
    public static function array(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * The string-keyed part of a value that should have been an object.
     *
     * @return array<string, mixed>
     */
    public static function object(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $object = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $object[$key] = $item;
            }
        }

        return $object;
    }

    /**
     * A list of strings, with anything that is not one dropped - for `Tags` and the like.
     *
     * @return list<string>
     */
    public static function strings(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $strings = [];

        foreach ($value as $item) {
            if (is_scalar($item)) {
                $strings[] = (string) $item;
            }
        }

        return $strings;
    }

    /**
     * Tags, from either of the two shapes Saasu writes them in.
     *
     * A contact, invoice or journal carries `"Tags": ["Advisor", "Business"]`. The search
     * results carry the same thing as ONE comma-separated string, `"Tags": "Advisor,Business"`,
     * because they come from a search index rather than the database. Both arrive here as a
     * list, with blanks dropped.
     *
     * @return list<string>
     */
    public static function tags(mixed $value): array
    {
        $tags = is_string($value) ? explode(',', $value) : self::strings($value);

        return array_values(array_filter(
            array_map('trim', $tags),
            static fn (string $tag): bool => $tag !== ''
        ));
    }

    /**
     * A list of nested objects mapped into entities. Anything that is not an object is skipped
     * rather than mapped, so one malformed element does not take the others with it.
     *
     * @template T
     * @param  callable(array<string, mixed>): T  $map
     * @return list<T>
     */
    public static function objects(mixed $value, callable $map): array
    {
        if (!is_array($value)) {
            return [];
        }

        $mapped = [];

        foreach ($value as $item) {
            if (is_array($item)) {
                $mapped[] = $map(self::object($item));
            }
        }

        return $mapped;
    }

    /**
     * A nested object mapped into an entity, or null when the field was null or absent.
     *
     * @template T
     * @param  callable(array<string, mixed>): T  $map
     * @return T|null
     */
    public static function nested(mixed $value, callable $map): mixed
    {
        return is_array($value) && $value !== [] ? $map(self::object($value)) : null;
    }

    /**
     * An INSTANT - a `...Utc` field - as a DateTimeImmutable in UTC.
     *
     * THE TIMEZONE IS ASSUMED UTC WHEN THE STRING DOES NOT CARRY ONE. `CreatedDateUtc` and
     * `LastModifiedDateUtc` are documented as UTC and usually arrive with a `Z`, but the
     * documentation's own samples also show them with `+10:00`, which is what a .NET server in
     * Sydney serialises a local DateTime as. Both are parsed as written and converted, and a
     * bare string is read as UTC rather than in PHP's own default timezone - the difference is
     * ten or eleven hours, silently, depending on how the box happens to be configured.
     *
     * Not for transaction dates - see date().
     */
    public static function datetime(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $utc = new \DateTimeZone('UTC');

        try {
            $parsed = new \DateTimeImmutable($value, $utc);
        } catch (\Exception) {
            return null;
        }

        return $parsed->setTimezone($utc);
    }

    /**
     * A CALENDAR DATE - `TransactionDate`, `DueDate`, `Date` - as midnight on that day, UTC.
     *
     * NOT CONVERTED BETWEEN TIMEZONES, deliberately and unlike datetime(). An invoice dated
     * the 24th is dated the 24th wherever it is read. Saasu writes these as
     * `"2015-01-24T00:00:00"` - a date with a meaningless midnight attached - and converting
     * that between zones is how an invoice moves to the 23rd. So the wall-clock date is read
     * off the string AS WRITTEN, any offset is ignored, and what comes back is midnight UTC on
     * that day, whose `format('Y-m-d')` is the date Saasu meant.
     *
     * The one thing to avoid is calling setTimezone() on the result: that reintroduces the
     * conversion this exists to prevent.
     *
     * AN OPEN QUESTION, recorded rather than guessed at: Saasu's documented payment sample writes
     * its date as `2026-09-12T14:00:00Z`, which is midnight on the 13th in Sydney expressed in UTC.
     * If real responses do that, the literal date here is the day before. The invoice sample
     * writes `2015-01-24T00:00:00` and does not. The harness's `lists` exercise prints both.
     */
    public static function date(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || preg_match('/^(\d{4}-\d{2}-\d{2})/', trim($value), $match) !== 1) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $match[1], new \DateTimeZone('UTC'));

        return $date === false ? null : $date;
    }

    /**
     * A LOCAL DATE AND TIME - a timesheet's start and finish - with its wall clock kept as written.
     *
     * Not converted to UTC, for the reason date() is not: a shift that started at 9am started at
     * 9am. An offset in the string is kept as the value's timezone, so `format('H:i')` answers
     * `09:00` whether Saasu wrote `2026-09-13T09:00:00` or `2026-09-13T09:00:00+10:00`. A
     * string with no offset is labelled UTC, which is a label and not a claim.
     */
    public static function localDateTime(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * A calendar date on its way OUT, as `YYYY-MM-DD` - the form every date filter takes.
     *
     * Read off the value's own wall clock without conversion, for the reason date() gives: a
     * Sydney-local DateTime for the 1st of July is the 1st of July, not the 30th of June.
     */
    public static function dateString(\DateTimeInterface|string $value): string
    {
        if (is_string($value)) {
            $parsed = self::date($value);

            if ($parsed === null) {
                throw new InvalidArgumentException(sprintf(
                    'Saasu expects a date as YYYY-MM-DD; "%s" is not one.',
                    $value
                ));
            }

            return $parsed->format('Y-m-d');
        }

        return $value->format('Y-m-d');
    }

    /**
     * A local date and time on its way out - a timesheet's start, an activity's due time - as
     * `YYYY-MM-DDTHH:MM:SS` with no offset.
     *
     * Written as the value's own wall clock. Saasu stores these as local times in the file's
     * timezone, so a caller who means "9am" passes a DateTime that reads 9am, in whatever zone
     * it happens to be in.
     */
    public static function localDateTimeString(\DateTimeInterface|string $value): string
    {
        if (is_string($value)) {
            try {
                $value = new \DateTimeImmutable($value);
            } catch (\Exception) {
                throw new InvalidArgumentException(sprintf('"%s" is not a date and time Saasu can read.', $value));
            }
        }

        return $value->format('Y-m-d\TH:i:s');
    }

    /**
     * A backed enum case matched case-insensitively, or null.
     *
     * Saasu is inconsistent about case between its documentation, its filters and its
     * responses - `AttachedToType` is documented as `Sale` and returned as `sale`, and the
     * account-level filter takes `header` while the field reads `Header`. A strict tryFrom()
     * would answer null for a value that is perfectly recognisable.
     *
     * @template T of \BackedEnum
     * @param  class-string<T>  $enum
     * @return T|null
     */
    public static function enum(string $enum, mixed $value): ?\BackedEnum
    {
        if (!is_scalar($value)) {
            return null;
        }

        $exact = $enum::tryFrom(is_int($value) ? $value : (string) $value);

        if ($exact !== null) {
            return $exact;
        }

        foreach ($enum::cases() as $case) {
            if (strcasecmp((string) $case->value, (string) $value) === 0) {
                return $case;
            }
        }

        return null;
    }

    /**
     * A value that must be present, for a named constructor whose whole job is to refuse an
     * empty one.
     */
    public static function required(?string $value, string $message): string
    {
        if ($value === null || trim($value) === '') {
            throw new InvalidArgumentException($message);
        }

        return trim($value);
    }

    /**
     * A positive id, for every method that puts one in a path.
     */
    public static function id(int $id, string $what): int
    {
        if ($id < 1) {
            throw new InvalidArgumentException(sprintf('A %s id must be a positive integer; %d was given.', $what, $id));
        }

        return $id;
    }
}
