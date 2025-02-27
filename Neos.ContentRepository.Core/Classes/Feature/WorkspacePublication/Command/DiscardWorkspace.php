<?php

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

declare(strict_types=1);

namespace Neos\ContentRepository\Core\Feature\WorkspacePublication\Command;

use Neos\ContentRepository\Core\CommandHandler\CommandInterface;
use Neos\ContentRepository\Core\SharedModel\Workspace\ContentStreamId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;

/**
 * Discard a workspace's changes
 *
 * @api commands are the write-API of the ContentRepository
 */
final readonly class DiscardWorkspace implements CommandInterface
{
    /**
     * @param WorkspaceName $workspaceName Name of the affected workspace
     * @param ContentStreamId $newContentStreamId The id of the newly forked content stream with no changes
     */
    private function __construct(
        public WorkspaceName $workspaceName,
        public ContentStreamId $newContentStreamId
    ) {
    }

    /**
     * @param WorkspaceName $workspaceName Name of the affected workspace
     */
    public static function create(WorkspaceName $workspaceName): self
    {
        return new self($workspaceName, ContentStreamId::create());
    }

    public static function fromArray(array $array): self
    {
        return new self(
            WorkspaceName::fromString($array['workspaceName']),
            isset($array['newContentStreamId']) ? ContentStreamId::fromString($array['newContentStreamId']) : ContentStreamId::create(),
        );
    }

    /**
     * Call this method if you want to run this command fully deterministically, f.e. during test cases
     */
    public function withNewContentStreamId(ContentStreamId $newContentStreamId): self
    {
        return new self($this->workspaceName, $newContentStreamId);
    }
}
