<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Result;

use Hampel\Saasu\Api\Support\Cast;

/**
 * What an insert or update answers with: the record's id and its new `LastUpdatedId`.
 *
 * KEEP THE LastUpdatedId. It is what the next update of this record must send, and reading the
 * record again to get it spends a request.
 *
 * Saasu names the id differently on nearly every resource - `InsertedContactId`,
 * `InsertedEntityId`, `UpdatedItemId`, plain `Id` - and on some updates answers `"Id": 0`
 * rather than the id that was updated. This reads whichever arrived, and falls back to the id
 * the caller updated. Anything resource-specific - the invoice number Saasu generated, the
 * company an aggregate created - is on `raw`, with accessors for the documented ones.
 */
final class Saved implements \JsonSerializable
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?int $id,
        public readonly ?string $lastUpdatedId,
        public readonly ?\DateTimeImmutable $lastModified,
        public readonly array $raw = [],
    ) {
    }

    public static function fromResponse(ApiResponse $response, ?int $knownId = null): self
    {
        $body = $response->object();
        $id = null;

        foreach ($body as $key => $value) {
            if (preg_match('/^(Inserted|Updated)\w*Id$/', $key) === 1 || $key === 'Id') {
                $candidate = Cast::int($value);

                if ($candidate !== null && $candidate > 0) {
                    $id = $candidate;

                    break;
                }
            }
        }

        return new self(
            $id ?? $knownId,
            Cast::text($body['LastUpdatedId'] ?? null),
            Cast::datetime($body['UtcLastModified'] ?? $body['LastModified'] ?? $body['LastModifiedDateUtc'] ?? null),
            $body,
        );
    }

    /**
     * The invoice number Saasu generated, for an invoice inserted with `<Auto Number>`.
     */
    public function generatedInvoiceNumber(): ?string
    {
        return Cast::text($this->raw['GeneratedInvoiceNumber'] ?? null);
    }

    /**
     * Whether an invoice insert or update also emailed it.
     */
    public function sentToContact(): ?bool
    {
        return Cast::bool($this->raw['SentToContact'] ?? null);
    }

    /**
     * The company a contact aggregate inserted or updated.
     */
    public function companyId(): ?int
    {
        $id = Cast::int($this->raw['CompanyId'] ?? null);

        return $id !== null && $id > 0 ? $id : null;
    }

    public function contactManagerId(): ?int
    {
        $id = Cast::int($this->raw['ContactManagerId'] ?? null);

        return $id !== null && $id > 0 ? $id : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->raw;
    }
}
