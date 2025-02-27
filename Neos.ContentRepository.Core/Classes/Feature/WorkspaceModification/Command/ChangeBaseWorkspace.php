<?php

declare(strict_types=1);

namespace Neos\ContentRepository\Core\Feature\WorkspaceModification\Command;

use Neos\ContentRepository\Core\CommandHandler\CommandInterface;
use Neos\ContentRepository\Core\SharedModel\Workspace\ContentStreamId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;

/**
 * Changes the base workspace of a given workspace, identified by $workspaceName.
 *
 * @api commands are the write-API of the ContentRepository
 */
final readonly class ChangeBaseWorkspace implements CommandInterface
{
    /**
     * @param WorkspaceName $workspaceName Name of the affected workspace
     * @param WorkspaceName $baseWorkspaceName Name of the new base workspace
     * @param ContentStreamId $newContentStreamId The id of the new content stream id that will be assigned to the workspace
     */
    private function __construct(
        public WorkspaceName $workspaceName,
        public WorkspaceName $baseWorkspaceName,
        public ContentStreamId $newContentStreamId,
    ) {
    }

    /**
     * @param WorkspaceName $workspaceName Name of the affected workspace
     * @param WorkspaceName $baseWorkspaceName Name of the new base workspace
     */
    public static function create(WorkspaceName $workspaceName, WorkspaceName $baseWorkspaceName): self
    {
        return new self($workspaceName, $baseWorkspaceName, ContentStreamId::create());
    }

    public static function fromArray(array $array): self
    {
        return new self(
            WorkspaceName::fromString($array['workspaceName']),
            WorkspaceName::fromString($array['baseWorkspaceName']),
            isset($array['newContentStreamId']) ? ContentStreamId::fromString($array['newContentStreamId']) : ContentStreamId::create(),
        );
    }

    /**
     * During the publish process, we create a new content stream.
     *
     * This method adds its ID, so that the command
     * can run fully deterministic - we need this for the test cases.
     */
    public function withNewContentStreamId(ContentStreamId $newContentStreamId): self
    {
        return new self($this->workspaceName, $this->baseWorkspaceName, $newContentStreamId);
    }
}
