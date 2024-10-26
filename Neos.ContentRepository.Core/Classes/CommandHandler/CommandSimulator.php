<?php

declare(strict_types=1);

namespace Neos\ContentRepository\Core\CommandHandler;

use Neos\ContentRepository\Core\EventStore\DecoratedEvent;
use Neos\ContentRepository\Core\EventStore\EventInterface;
use Neos\ContentRepository\Core\EventStore\EventNormalizer;
use Neos\ContentRepository\Core\EventStore\EventsToPublish;
use Neos\ContentRepository\Core\Feature\RebaseableCommand;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentGraphProjectionInterface;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\EventStore\Helper\InMemoryEventStore;
use Neos\EventStore\Model\Event\EventMetadata;
use Neos\EventStore\Model\Event\SequenceNumber;
use Neos\EventStore\Model\Events;
use Neos\EventStore\Model\EventStream\EventStreamInterface;
use Neos\EventStore\Model\EventStream\ExpectedVersion;
use Neos\EventStore\Model\EventStream\VirtualStreamName;

/**
 * The CommandSimulator is used during the publishing process, for partial publishing and workspace rebasing.
 *
 * For this case, we want to apply commands including their constraint checks step by step, to see whether this
 * set of commands applies cleanly without errors, and which events would be created by them, but we do NOT
 * want to commit the updated projections or events.
 *
 * Internally, we do the following:
 * - Create a database transaction in the GraphProjection which we will roll back lateron (to dry-run
 * projection updates) (via {@see CommandSimulator::run()}).
 * - Create an InMemoryEventStore which buffers created events by command handlers.
 * - execute all commands via {@see CommandSimulator::handle()}
 * - -> this will do all constraint checks based on the projection in the open transaction (so it sees
 * previously modified projection state which is not committed)
 * - -> it will run the command handlers, buffer all emitted events in the InMemoryEventStore
 *   -> note to avoid full recursion the workspace command handler is not included in the bus
 * - -> update the GraphProjection, but WITHOUT committing the transaction.
 *
 * This is quite performant because we do not need to fork a new content stream.
 *
 * @internal
 */
final readonly class CommandSimulator
{
    public function __construct(
        private ContentGraphProjectionInterface $contentRepositoryProjection,
        private EventNormalizer $eventNormalizer,
        private CommandBus $commandBus,
        private InMemoryEventStore $inMemoryEventStore,
        private WorkspaceName $workspaceNameToSimulateIn,
    ) {
    }

    /**
     * @template T
     * @param callable(callable(RebaseableCommand): void): T $fn
     * @return T
     */
    public function run(callable $fn): mixed
    {
        return $this->contentRepositoryProjection->inSimulation(fn () => $fn($this->handle(...)));
    }

    /**
     * Handle a command within a running simulation, otherwise throw.
     *
     * We will automatically copy given commands to the workspace this simulation
     * is running in to ensure consistency in the simulations constraint checks.
     */
    private function handle(RebaseableCommand $rebaseableCommand): void
    {
        // FIXME: Check if workspace already matches and skip this, e.g. $commandInWorkspace = $command->getWorkspaceName()->equals($this->workspaceNameToSimulateIn) ? $command : $command->createCopyForWorkspace($this->workspaceNameToSimulateIn);
        // when https://github.com/neos/neos-development-collection/pull/5298 is merged
        $commandInWorkspace = $rebaseableCommand->originalCommand->createCopyForWorkspace($this->workspaceNameToSimulateIn);

        $eventsToPublish = $this->commandBus->handle($commandInWorkspace);
        if (!$eventsToPublish instanceof EventsToPublish) {
            throw new \RuntimeException(sprintf('CommandSimulator expects direct EventsToPublish to be returned when handling %s', $rebaseableCommand->originalCommand::class));
        }

        if ($eventsToPublish->events->isEmpty()) {
            return;
        }

        $normalizedEvents = Events::fromArray(
            $eventsToPublish->events->map(function (EventInterface|DecoratedEvent $event) use (
                $rebaseableCommand
            ) {
                $metadata = $event instanceof DecoratedEvent ? $event->eventMetadata?->value ?? [] : [];
                $decoratedEvent = DecoratedEvent::create($event, metadata: EventMetadata::fromArray(
                    array_merge($metadata, $rebaseableCommand->initiatingMetaData->value ?? [])
                ));
                return $this->eventNormalizer->normalize($decoratedEvent);
            })
        );

        $sequenceNumberBeforeCommit = $this->currentSequenceNumber();

        // The version of the stream in the IN MEMORY event store does not matter to us,
        // because this is only used in memory during the partial publish or rebase operation; so it cannot be written to
        // concurrently.
        // HINT: We cannot use $eventsToPublish->expectedVersion, because this is based on the PERSISTENT event stream (having different numbers)
        $this->inMemoryEventStore->commit(
            $eventsToPublish->streamName,
            $normalizedEvents,
            ExpectedVersion::ANY()
        );

        // fetch all events that were now committed. Plus one because the first sequence number is one too otherwise we get one event to many.
        // (all elephants shall be placed shamefully placed on my head)
        $eventStream = $this->inMemoryEventStore->load(VirtualStreamName::all())->withMinimumSequenceNumber(
            $sequenceNumberBeforeCommit->next()
        );

        foreach ($eventStream as $eventEnvelope) {
            $event = $this->eventNormalizer->denormalize($eventEnvelope->event);

            if (!$this->contentRepositoryProjection->canHandle($event)) {
                continue;
            }

            $this->contentRepositoryProjection->apply($event, $eventEnvelope);
        }
    }

    public function currentSequenceNumber(): SequenceNumber
    {
        foreach ($this->inMemoryEventStore->load(VirtualStreamName::all())->backwards()->limit(1) as $eventEnvelope) {
            return $eventEnvelope->sequenceNumber;
        }
        return SequenceNumber::none();
    }

    public function eventStream(): EventStreamInterface
    {
        return $this->inMemoryEventStore->load(VirtualStreamName::all());
    }
}
