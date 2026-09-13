<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Result;

/**
 * A file Saasu generated - an invoice, a statement, a payslip - as the bytes it sent.
 *
 * Saasu documents these endpoints as returning "raw PDF file content" and nothing more, so the
 * content type and filename are read from the response headers when they were sent and are
 * null when they were not.
 */
final class Document
{
    public function __construct(
        public readonly string $content,
        public readonly ?string $contentType = null,
        public readonly ?string $filename = null,
        public readonly ResponseMeta $meta = new ResponseMeta(),
    ) {
    }

    public static function fromMeta(string $content, ResponseMeta $meta): self
    {
        return new self($content, $meta->contentType(), self::filenameFrom($meta->header('Content-Disposition')), $meta);
    }

    /**
     * Whether the bytes are a PDF, by their signature rather than by any header - a proxy error
     * page arrives with a content type of its own choosing.
     */
    public function isPdf(): bool
    {
        return str_starts_with($this->content, '%PDF-');
    }

    public function size(): int
    {
        return strlen($this->content);
    }

    public static function filenameFrom(?string $disposition): ?string
    {
        if ($disposition === null) {
            return null;
        }

        if (preg_match("/filename\\*=(?:UTF-8'')?([^;]+)/i", $disposition, $match) === 1) {
            return basename(rawurldecode(trim($match[1], " \"'")));
        }

        if (preg_match('/filename="?([^";]+)"?/i', $disposition, $match) === 1) {
            return basename(trim($match[1]));
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['contentType' => $this->contentType, 'filename' => $this->filename, 'size' => $this->size()];
    }
}
