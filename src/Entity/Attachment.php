<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Support\Cast;

/**
 * An attachment with its contents, from `InvoiceAttachment/{id}`.
 *
 * THE CONTENTS ARE DOCUMENTED AS "AN ARRAY OF BYTES". Saasu's server is .NET, whose JSON
 * serialiser writes a byte array as a base64 string; a literal array of numbers would be the
 * other reading. content() decodes either, and answers null when there is nothing - which is
 * what the documentation's own sample shows. Nothing here has observed a real one.
 */
final class Attachment extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $description = null,
        public readonly ?int $itemIdAttachedTo = null,
        public readonly ?bool $allowOverwrite = null,
        public readonly mixed $data = null,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): static
    {
        return new self(
            Cast::text($row['Name'] ?? null),
            Cast::text($row['Description'] ?? null),
            Cast::int($row['ItemIdAttachedTo'] ?? null),
            Cast::bool($row['AllowExistingAttachmentToBeOverwritten'] ?? null),
            $row['AttachmentData'] ?? null,
            $row,
        );
    }

    /**
     * The attachment's bytes, decoded.
     */
    public function content(): ?string
    {
        if (is_string($this->data)) {
            $decoded = base64_decode($this->data, true);

            return $decoded === false ? null : $decoded;
        }

        if (is_array($this->data) && $this->data !== []) {
            $bytes = '';

            foreach ($this->data as $byte) {
                $byte = Cast::int($byte);

                if ($byte === null || $byte < 0 || $byte > 255) {
                    return null;
                }

                $bytes .= chr($byte);
            }

            return $bytes;
        }

        return null;
    }

    /**
     * Without the contents, which may be megabytes.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'itemIdAttachedTo' => $this->itemIdAttachedTo,
            'size' => strlen($this->content() ?? ''),
        ];
    }
}
