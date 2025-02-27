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

namespace Neos\ContentRepository\Core\Feature\NodeReferencing\Command;

use Neos\ContentRepository\Core\DimensionSpace\OriginDimensionSpacePoint;
use Neos\ContentRepository\Core\Feature\Common\RebasableToOtherWorkspaceInterface;
use Neos\ContentRepository\Core\Feature\NodeReferencing\Dto\SerializedNodeReferences;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;

/**
 * Set property values for a given node.
 *
 * The property values contain the serialized types already, and include type information.
 *
 * @internal implementation detail, use {@see SetNodeReferences} instead.
 */
final readonly class SetSerializedNodeReferences implements
    \JsonSerializable,
    RebasableToOtherWorkspaceInterface
{
    /**
     * @param WorkspaceName $workspaceName The workspace in which the create operation is to be performed
     * @param NodeAggregateId $sourceNodeAggregateId The identifier of the node aggregate to set references
     * @param OriginDimensionSpacePoint $sourceOriginDimensionSpacePoint The dimension space for which the references should be set
     * @param SerializedNodeReferences $references Serialized reference(s) to set
     */
    private function __construct(
        public WorkspaceName $workspaceName,
        public NodeAggregateId $sourceNodeAggregateId,
        public OriginDimensionSpacePoint $sourceOriginDimensionSpacePoint,
        public SerializedNodeReferences $references,
    ) {
    }

    /**
     * @param WorkspaceName $workspaceName The workspace in which the create operation is to be performed
     * @param NodeAggregateId $sourceNodeAggregateId The identifier of the node aggregate to set references
     * @param OriginDimensionSpacePoint $sourceOriginDimensionSpacePoint The dimension space for which the references should be set
     * @param SerializedNodeReferences $references Serialized reference(s) to set
     */
    public static function create(WorkspaceName $workspaceName, NodeAggregateId $sourceNodeAggregateId, OriginDimensionSpacePoint $sourceOriginDimensionSpacePoint, SerializedNodeReferences $references): self
    {
        return new self($workspaceName, $sourceNodeAggregateId, $sourceOriginDimensionSpacePoint, $references);
    }

    public static function fromArray(array $array): self
    {
        return new self(
            WorkspaceName::fromString($array['workspaceName']),
            NodeAggregateId::fromString($array['sourceNodeAggregateId']),
            OriginDimensionSpacePoint::fromArray($array['sourceOriginDimensionSpacePoint']),
            SerializedNodeReferences::fromArray($array['references']),
        );
    }

    /**
     * @internal
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
            $this->sourceNodeAggregateId,
            $this->sourceOriginDimensionSpacePoint,
            $this->references,
        );
    }
}
