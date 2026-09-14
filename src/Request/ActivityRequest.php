<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Request;

use Hampel\Saasu\Api\Enum\AttachedToType;
use Hampel\Saasu\Api\Support\Cast;

/**
 * An activity to insert, or the changes to one.
 *
 * `AttachedToType` and `AttachedToId` go together - attachedTo() sets both. `OwnerEmail` names the
 * user responsible, who must have access to the file.
 */
final class ActivityRequest extends Payload
{
    protected const WRITABLE = [
        'Title',
        'Details',
        'ActivityType',
        'Done',
        'Due',
        'OwnerEmail',
        'AttachedToType',
        'AttachedToId',
        'Tags',
        'LastUpdatedId',
    ];

    public static function of(string $title): self
    {
        return self::make()->withTitle(Cast::required($title, 'An activity needs a title.'));
    }

    public function attachedTo(AttachedToType $type, int $id): self
    {
        return $this->withAttachedToType($type)->withAttachedToId(Cast::id($id, $type->value));
    }

    public function withTitle(?string $title): static
    {
        return $this->with('Title', $title);
    }

    public function withDetails(?string $details): static
    {
        return $this->with('Details', $details);
    }

    public function withActivityType(?string $activityType): static
    {
        return $this->with('ActivityType', $activityType);
    }

    public function withDone(?bool $done): static
    {
        return $this->with('Done', $done);
    }

    public function withDue(\DateTimeInterface|string|null $due): static
    {
        return $this->putDate('Due', $due);
    }

    public function withOwnerEmail(?string $ownerEmail): static
    {
        return $this->with('OwnerEmail', $ownerEmail);
    }

    public function withAttachedToType(AttachedToType|string|null $attachedToType): static
    {
        return $this->with('AttachedToType', $attachedToType instanceof \BackedEnum ? $attachedToType->value : $attachedToType);
    }

    public function withAttachedToId(?int $attachedToId): static
    {
        return $this->with('AttachedToId', $attachedToId);
    }

    /**
     * @param  list<string>  $tags
     */
    public function withTags(array $tags): static
    {
        return $this->putTags($tags);
    }

    public function withLastUpdatedId(?string $lastUpdatedId): static
    {
        return $this->with('LastUpdatedId', $lastUpdatedId);
    }
}
