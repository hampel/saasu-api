<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Request;

use Hampel\Saasu\Api\Support\Cast;

/**
 * An attachment to upload.
 *
 *     AttachmentRequest::of(5093684, 'remittance.pdf', file_get_contents($path))
 *
 * THE CONTENTS ARE SENT BASE64-ENCODED, which is how a .NET server reads the "array of bytes"
 * Saasu documents for `AttachmentData`. Nothing here has observed an upload succeed; the
 * harness's attachment exercise is what settles it.
 *
 * AN ATTACHMENT OF THE SAME NAME IS NOT REPLACED unless allowOverwrite() is set.
 */
final class AttachmentRequest extends Payload
{
    protected const WRITABLE = ['Name', 'Description', 'ItemIdAttachedTo', 'AllowExistingAttachmentToBeOverwritten'];

    public static function of(int $attachedToId, string $name, string $content, ?string $description = null): self
    {
        return self::make()
            ->with('ItemIdAttachedTo', Cast::id($attachedToId, 'record to attach to'))
            ->with('Name', Cast::required($name, 'An attachment needs a file name.'))
            ->with('AttachmentData', base64_encode($content))
            ->with('Description', $description);
    }

    public function allowOverwrite(bool $allow = true): self
    {
        return $this->with('AllowExistingAttachmentToBeOverwritten', $allow);
    }

    public function size(): int
    {
        $data = $this->get('AttachmentData');

        return is_string($data) ? strlen((string) base64_decode($data, true)) : 0;
    }

    /**
     * Without the contents.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['name' => $this->get('Name'), 'itemIdAttachedTo' => $this->get('ItemIdAttachedTo'), 'size' => $this->size()];
    }
}
