<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Enum\AttachedToType;
use Hampel\Saasu\Api\Support\Cast;

/**
 * A task or note - a to-do, a call to make - optionally attached to a contact, an employee, a
 * sale or a purchase.
 *
 * THE LIST CARRIES LESS THAN THE RECORD. `Activities` answers summaries without `Details` or
 * `Attachments`; both are null and empty here until the activity is read by id.
 *
 * `ActivityType` is not a fixed list: it is any tag in the file that has the activity flag set,
 * and Saasu ignores a value that is not one rather than refusing it.
 *
 * `Due` IS A DATE. Measured 2026-09-14: an activity written due at 17:00 reads back as
 * `2026-09-14T00:00:00` - the time is dropped - so it is read as a calendar date, see Cast::date().
 */
final class Activity extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $lastUpdatedId = null,
        public readonly ?string $activityType = null,
        public readonly ?bool $done = null,
        public readonly ?\DateTimeImmutable $due = null,
        public readonly ?string $title = null,
        public readonly ?string $details = null,
        public readonly ?string $ownerFirstName = null,
        public readonly ?string $ownerLastName = null,
        public readonly ?string $ownerEmail = null,
        public readonly ?string $attachedToType = null,
        public readonly ?int $attachedToId = null,
        /** @var list<string> */
        public readonly array $tags = [],
        /** @var list<AttachmentInfo> */
        public readonly array $attachments = [],
        public readonly ?\DateTimeImmutable $createdDateUtc = null,
        public readonly ?\DateTimeImmutable $lastModifiedDateUtc = null,
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
            Cast::int($row['Id'] ?? null),
            Cast::text($row['LastUpdatedId'] ?? null),
            Cast::text($row['ActivityType'] ?? null),
            Cast::bool($row['Done'] ?? null),
            Cast::date($row['Due'] ?? null),
            Cast::text($row['Title'] ?? null),
            Cast::text($row['Details'] ?? null),
            Cast::text($row['OwnerFirstName'] ?? null),
            Cast::text($row['OwnerLastName'] ?? null),
            Cast::text($row['OwnerEmail'] ?? null),
            Cast::text($row['AttachedToType'] ?? null),
            Cast::int($row['AttachedToId'] ?? null),
            Cast::tags($row['Tags'] ?? null),
            Cast::objects($row['Attachments'] ?? null, AttachmentInfo::fromArray(...)),
            Cast::datetime($row['CreatedDateUtc'] ?? null),
            Cast::datetime($row['LastModifiedDateUtc'] ?? null),
            $row,
        );
    }

    public function attachedTo(): ?AttachedToType
    {
        return Cast::enum(AttachedToType::class, $this->attachedToType);
    }
}
