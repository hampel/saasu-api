<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Endpoint;

use Hampel\Saasu\Api\Entity\FileIdentity;
use Hampel\Saasu\Api\Entity\FileSummary;
use Hampel\Saasu\Api\Request\FileIdentityRequest;
use Hampel\Saasu\Api\Result\Saved;

/**
 * The file - the business - the client is configured for, at `FileIdentity`, and the files the
 * login can reach, at `FileIdentities`.
 */
final class Files extends Endpoint
{
    /**
     * The configured file's business identity.
     */
    public function identity(): FileIdentity
    {
        return $this->apiObject('FileIdentity', FileIdentity::fromArray(...));
    }

    /**
     * Update the configured file's business identity, built from() the identity as read.
     */
    public function updateIdentity(FileIdentityRequest $request): Saved
    {
        $this->assertUpdatable($request, 'file identity');

        return $this->apiSave('PUT', 'FileIdentity', $request, $this->connection->config()->fileId);
    }

    /**
     * Every file the credential can reach - sent without a file id, so it works on a client that
     * has not been given one yet.
     *
     * @return list<FileSummary>
     */
    public function all(): array
    {
        $unscoped = new self($this->connection->withoutFile());

        return iterator_to_array($unscoped->apiEach('FileIdentities', 'FileIdentities', FileSummary::fromArray(...)), false);
    }
}
