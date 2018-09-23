<?php

namespace Neos\ContentGraph\DoctrineDbalAdapter\Domain\Projection;

/*
 * This file is part of the Neos.ContentGraph package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */
use Doctrine\DBAL\Connection;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeAggregateIdentifier;
use Neos\EventSourcedContentRepository\Domain\ValueObject\NodeIdentifier;
use Neos\EventSourcedContentRepository\Domain\ValueObject\NodeName;
use Neos\EventSourcedContentRepository\Domain\ValueObject\NodeTypeName;
use Neos\Flow\Annotations as Flow;

/**
 * A read model to read node aggregates from the projection
 */
class NodeAggregate
{

    /**
     * @var NodeAggregateIdentifier
     */
    public $nodeAggregateIdentifier;

    /**
     * @var NodeTypeName
     */
    public $nodeTypeName;

    /**
     * NodeAggregate constructor.
     *
     * @param NodeAggregateIdentifier $nodeAggregateIdentifier
     * @param NodeTypeName $nodeTypeName
     */
    public function __construct(NodeAggregateIdentifier $nodeAggregateIdentifier, NodeTypeName $nodeTypeName)
    {
        $this->nodeAggregateIdentifier = $nodeAggregateIdentifier;
        $this->nodeTypeName = $nodeTypeName;
    }

    /**
     * @param array $databaseRow
     * @return static
     */
    public static function fromDatabaseRow(array $databaseRow)
    {
        return new static(
            new NodeAggregateIdentifier($databaseRow['nodeaggregateidentifier']),
            new NodeTypeName($databaseRow['nodetypename'])
        );
    }
}
