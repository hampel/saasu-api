<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Support;

/**
 * Taking credentials out of text before it is written anywhere.
 */
final class Redact
{
    public const MASK = '[redacted]';

    /**
     * A URI with any `wsAccessKey` value masked, in whatever case it was written.
     *
     * The legacy access key is a query parameter, so it is part of every URI a WsAccessKey
     * client requests - and Saasu writes it back into `_links` hrefs too, as the documentation's
     * own FileIdentities sample shows (`?wsaccesskey=869af5a2...&fileid=1`). Everything this
     * package logs or puts in an exception message passes through here first.
     */
    public static function uri(string $uri): string
    {
        return (string) preg_replace('/([?&]wsaccesskey=)[^&#\s]*/i', '$1' . self::MASK, $uri);
    }
}
