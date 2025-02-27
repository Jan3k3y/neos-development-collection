<?php

declare(strict_types=1);

namespace Neos\Neos\FrontendRouting\CatchUpHook;

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\EventStore\EventInterface;
use Neos\ContentRepository\Core\Feature\NodeModification\Event\NodePropertiesWereSet;
use Neos\ContentRepository\Core\Feature\NodeMove\Event\NodeAggregateWasMoved;
use Neos\ContentRepository\Core\Feature\NodeRemoval\Event\NodeAggregateWasRemoved;
use Neos\ContentRepository\Core\Feature\SubtreeTagging\Event\SubtreeWasTagged;
use Neos\ContentRepository\Core\Projection\CatchUpHook\CatchUpHookInterface;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\Subscription\SubscriptionStatus;
use Neos\EventStore\Model\EventEnvelope;
use Neos\Flow\Mvc\Routing\RouterCachingService;
use Neos\Neos\FrontendRouting\Exception\NodeNotFoundException;
use Neos\Neos\FrontendRouting\Projection\DocumentNodeInfo;
use Neos\Neos\FrontendRouting\Projection\DocumentUriPathFinder;

final class RouterCacheHook implements CatchUpHookInterface
{
    /**
     * Runtime cache to collect tags until they can get flushed.
     * @var string[]
     */
    private array $tagsToFlush = [];

    public function __construct(
        private readonly DocumentUriPathFinder $documentUriPathFinder,
        private readonly RouterCachingService $routerCachingService,
    ) {
    }

    public function onBeforeCatchUp(SubscriptionStatus $subscriptionStatus): void
    {
        // Nothing to do here
    }

    public function onBeforeEvent(EventInterface $eventInstance, EventEnvelope $eventEnvelope): void
    {
        match ($eventInstance::class) {
            NodeAggregateWasRemoved::class => $this->onBeforeNodeAggregateWasRemoved($eventInstance),
            NodePropertiesWereSet::class => $this->onBeforeNodePropertiesWereSet($eventInstance),
            NodeAggregateWasMoved::class => $this->onBeforeNodeAggregateWasMoved($eventInstance),
            SubtreeWasTagged::class => $this->onBeforeSubtreeWasTagged($eventInstance),
            default => null
        };
    }

    public function onAfterEvent(EventInterface $eventInstance, EventEnvelope $eventEnvelope): void
    {
        match ($eventInstance::class) {
            NodeAggregateWasRemoved::class => $this->flushAllCollectedTags(),
            NodePropertiesWereSet::class => $this->flushAllCollectedTags(),
            NodeAggregateWasMoved::class => $this->flushAllCollectedTags(),
            SubtreeWasTagged::class => $this->flushAllCollectedTags(),
            default => null
        };
    }

    public function onAfterBatchCompleted(): void
    {
        // Nothing to do here
    }

    public function onAfterCatchUp(): void
    {
        // Nothing to do here
    }

    private function onBeforeSubtreeWasTagged(SubtreeWasTagged $event): void
    {
        if (!$event->workspaceName->isLive()) {
            return;
        }

        foreach ($event->affectedDimensionSpacePoints as $dimensionSpacePoint) {
            $node = $this->findDocumentNodeInfoByIdAndDimensionSpacePoint($event->nodeAggregateId, $dimensionSpacePoint);
            if ($node === null) {
                // Probably not a document node
                continue;
            }

            $this->collectTagsToFlush($node);

            $descendantsOfNode = $this->documentUriPathFinder->getDescendantsOfNode($node);
            array_map($this->collectTagsToFlush(...), iterator_to_array($descendantsOfNode));
        }
    }

    private function onBeforeNodeAggregateWasRemoved(NodeAggregateWasRemoved $event): void
    {
        if (!$event->workspaceName->isLive()) {
            return;
        }

        foreach ($event->affectedCoveredDimensionSpacePoints as $dimensionSpacePoint) {
            $node = $this->findDocumentNodeInfoByIdAndDimensionSpacePoint($event->nodeAggregateId, $dimensionSpacePoint);
            if ($node === null) {
                // Probably not a document node
                continue;
            }

            $this->collectTagsToFlush($node);

            $descendantsOfNode = $this->documentUriPathFinder->getDescendantsOfNode($node);
            array_map($this->collectTagsToFlush(...), iterator_to_array($descendantsOfNode));
        }
    }

    private function onBeforeNodePropertiesWereSet(NodePropertiesWereSet $event): void
    {
        if (!$event->workspaceName->isLive()) {
            return;
        }

        $newPropertyValues = $event->propertyValues->getPlainValues();
        if (!isset($newPropertyValues['uriPathSegment'])) {
            return;
        }

        foreach ($event->affectedDimensionSpacePoints as $affectedDimensionSpacePoint) {
            $node = $this->findDocumentNodeInfoByIdAndDimensionSpacePoint($event->nodeAggregateId, $affectedDimensionSpacePoint);
            if ($node === null) {
                // probably not a document node
                continue;
            }

            $this->collectTagsToFlush($node);

            $descendantsOfNode = $this->documentUriPathFinder->getDescendantsOfNode($node);
            array_map($this->collectTagsToFlush(...), iterator_to_array($descendantsOfNode));
        }
    }

    private function onBeforeNodeAggregateWasMoved(NodeAggregateWasMoved $event): void
    {
        if (!$event->workspaceName->isLive()) {
            return;
        }

        foreach ($event->succeedingSiblingsForCoverage as $succeedingSiblingForCoverage) {
            $node = $this->findDocumentNodeInfoByIdAndDimensionSpacePoint(
                $event->nodeAggregateId,
                $succeedingSiblingForCoverage->dimensionSpacePoint
            );
            if (!$node) {
                // node probably no document node, skip
                continue;
            }

            $this->collectTagsToFlush($node);

            $descendantsOfNode = $this->documentUriPathFinder->getDescendantsOfNode($node);
            array_map($this->collectTagsToFlush(...), iterator_to_array($descendantsOfNode));
        }
    }

    private function collectTagsToFlush(DocumentNodeInfo $node): void
    {
        array_push($this->tagsToFlush, ...$node->getRouteTags()->getTags());
    }

    private function flushAllCollectedTags(): void
    {
        if ($this->tagsToFlush === []) {
            return;
        }

        $this->routerCachingService->flushCachesByTags($this->tagsToFlush);
        $this->tagsToFlush = [];
    }

    private function findDocumentNodeInfoByIdAndDimensionSpacePoint(NodeAggregateId $nodeAggregateId, DimensionSpacePoint $dimensionSpacePoint): ?DocumentNodeInfo
    {
        try {
            return $this->documentUriPathFinder->getByIdAndDimensionSpacePointHash(
                $nodeAggregateId,
                $dimensionSpacePoint->hash
            );
        } catch (NodeNotFoundException $_) {
            /** @noinspection BadExceptionsProcessingInspection */
            return null;
        }
    }
}
