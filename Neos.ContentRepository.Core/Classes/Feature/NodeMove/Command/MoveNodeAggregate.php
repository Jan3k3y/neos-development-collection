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

namespace Neos\ContentRepository\Core\Feature\NodeMove\Command;

use Neos\ContentRepository\Core\CommandHandler\CommandInterface;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\Feature\Common\RebasableToOtherWorkspaceInterface;
use Neos\ContentRepository\Core\Feature\NodeMove\Dto\RelationDistributionStrategy;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;

/**
 * The "Move node aggregate" command
 *
 * In `contentStreamId`
 * and `dimensionSpacePoint`,
 * move node aggregate `nodeAggregateId`
 * into `newParentNodeAggregateId` (or keep the current parent)
 * between `newPrecedingSiblingNodeAggregateId`
 * and `newSucceedingSiblingNodeAggregateId` (or as last of all siblings)
 * using `relationDistributionStrategy`
 *
 * Why can you specify **both** newPrecedingSiblingNodeAggregateId
 * and newSucceedingSiblingNodeAggregateId?
 * - it can happen that in one subgraph, only one of these match.
 * - See the PHPDoc of the attributes (a few lines down) for the exact behavior.
 *
 * @api commands are the write-API of the ContentRepository
 */
final readonly class MoveNodeAggregate implements
    CommandInterface,
    \JsonSerializable,
    RebasableToOtherWorkspaceInterface
{
    /**
     * @param WorkspaceName $workspaceName The workspace in which the move operation is to be performed
     * @param DimensionSpacePoint $dimensionSpacePoint This is one of the *covered* dimension space points of the node aggregate and not necessarily one of the occupied ones. This allows us to move virtual specializations only when using the scatter strategy
     * @param NodeAggregateId $nodeAggregateId The id of the node aggregate to move
     * @param RelationDistributionStrategy $relationDistributionStrategy The relation distribution strategy to be used ({@see RelationDistributionStrategy})
     * @param NodeAggregateId|null $newParentNodeAggregateId The id of the new parent node aggregate. If given, it enforces that all nodes in the given aggregate are moved into nodes of the parent aggregate, even if the given siblings belong to other parents. In latter case, those siblings are ignored
     * @param NodeAggregateId|null $newPrecedingSiblingNodeAggregateId The id of the new preceding sibling node aggregate. If given and no successor found, it is attempted to insert the moved nodes right after nodes of this aggregate. In dimension space points this aggregate does not cover, other siblings, in order of proximity, are tried to be used instead
     * @param NodeAggregateId|null $newSucceedingSiblingNodeAggregateId The id of the new succeeding sibling node aggregate. If given, it is attempted to insert the moved nodes right before nodes of this aggregate. In dimension space points this aggregate does not cover, the preceding sibling is tried to be used instead
     */
    private function __construct(
        public WorkspaceName $workspaceName,
        public DimensionSpacePoint $dimensionSpacePoint,
        public NodeAggregateId $nodeAggregateId,
        public RelationDistributionStrategy $relationDistributionStrategy,
        public ?NodeAggregateId $newParentNodeAggregateId,
        public ?NodeAggregateId $newPrecedingSiblingNodeAggregateId,
        public ?NodeAggregateId $newSucceedingSiblingNodeAggregateId,
    ) {
    }

    /**
     * @param WorkspaceName $workspaceName The workspace in which the move operation is to be performed
     * @param DimensionSpacePoint $dimensionSpacePoint This is one of the *covered* dimension space points of the node aggregate and not necessarily one of the occupied ones. This allows us to move virtual specializations only when using the scatter strategy
     * @param NodeAggregateId $nodeAggregateId The id of the node aggregate to move
     * @param RelationDistributionStrategy $relationDistributionStrategy The relation distribution strategy to be used ({@see RelationDistributionStrategy}).
     * @param NodeAggregateId|null $newParentNodeAggregateId The id of the new parent node aggregate. If given, it enforces that all nodes in the given aggregate are moved into nodes of the parent aggregate, even if the given siblings belong to other parents. In latter case, those siblings are ignored
     * @param NodeAggregateId|null $newPrecedingSiblingNodeAggregateId The id of the new preceding sibling node aggregate. If given and no successor found, it is attempted to insert the moved nodes right after nodes of this aggregate. In dimension space points this aggregate does not cover, other siblings, in order of proximity, are tried to be used instead
     * @param NodeAggregateId|null $newSucceedingSiblingNodeAggregateId The id of the new succeeding sibling node aggregate. If given, it is attempted to insert the moved nodes right before nodes of this aggregate. In dimension space points this aggregate does not cover, the preceding sibling is tried to be used instead
     */
    public static function create(WorkspaceName $workspaceName, DimensionSpacePoint $dimensionSpacePoint, NodeAggregateId $nodeAggregateId, RelationDistributionStrategy $relationDistributionStrategy, ?NodeAggregateId $newParentNodeAggregateId = null, ?NodeAggregateId $newPrecedingSiblingNodeAggregateId = null, ?NodeAggregateId $newSucceedingSiblingNodeAggregateId = null): self
    {
        return new self($workspaceName, $dimensionSpacePoint, $nodeAggregateId, $relationDistributionStrategy, $newParentNodeAggregateId, $newPrecedingSiblingNodeAggregateId, $newSucceedingSiblingNodeAggregateId);
    }

    public static function fromArray(array $array): self
    {
        return new self(
            WorkspaceName::fromString($array['workspaceName']),
            DimensionSpacePoint::fromArray($array['dimensionSpacePoint']),
            NodeAggregateId::fromString($array['nodeAggregateId']),
            isset($array['relationDistributionStrategy'])
                ? RelationDistributionStrategy::from($array['relationDistributionStrategy'])
                : RelationDistributionStrategy::default(),
            isset($array['newParentNodeAggregateId'])
                ? NodeAggregateId::fromString($array['newParentNodeAggregateId'])
                : null,
            isset($array['newPrecedingSiblingNodeAggregateId'])
                ? NodeAggregateId::fromString($array['newPrecedingSiblingNodeAggregateId'])
                : null,
            isset($array['newSucceedingSiblingNodeAggregateId'])
                ? NodeAggregateId::fromString($array['newSucceedingSiblingNodeAggregateId'])
                : null,
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }

    public function createCopyForWorkspace(
        WorkspaceName $targetWorkspaceName,
    ): self {
        return new self(
            $targetWorkspaceName,
            $this->dimensionSpacePoint,
            $this->nodeAggregateId,
            $this->relationDistributionStrategy,
            $this->newParentNodeAggregateId,
            $this->newPrecedingSiblingNodeAggregateId,
            $this->newSucceedingSiblingNodeAggregateId
        );
    }
}
