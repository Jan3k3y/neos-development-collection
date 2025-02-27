<?php

declare(strict_types=1);

namespace Neos\ContentRepositoryRegistry;

use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\Dimension\ContentDimensionSourceInterface;
use Neos\ContentRepository\Core\Factory\CommandHookFactoryInterface;
use Neos\ContentRepository\Core\Factory\CommandHooksFactory;
use Neos\ContentRepository\Core\Factory\ContentRepositoryFactory;
use Neos\ContentRepository\Core\Factory\ContentRepositoryServiceFactoryInterface;
use Neos\ContentRepository\Core\Factory\ContentRepositoryServiceInterface;
use Neos\ContentRepository\Core\Factory\ContentRepositorySubscriberFactories;
use Neos\ContentRepository\Core\Factory\ProjectionSubscriberFactory;
use Neos\ContentRepository\Core\NodeType\NodeTypeManager;
use Neos\ContentRepository\Core\Projection\CatchUpHook\CatchUpHookFactories;
use Neos\ContentRepository\Core\Projection\CatchUpHook\CatchUpHookFactoryInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentGraphProjectionFactoryInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentGraphReadModelInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentSubgraphInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\Projection\ProjectionFactoryInterface;
use Neos\ContentRepository\Core\Projection\ProjectionStateInterface;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryIds;
use Neos\ContentRepository\Core\Subscription\Store\SubscriptionStoreInterface;
use Neos\ContentRepository\Core\Subscription\SubscriptionId;
use Neos\ContentRepositoryRegistry\Exception\ContentRepositoryNotFoundException;
use Neos\ContentRepositoryRegistry\Exception\InvalidConfigurationException;
use Neos\ContentRepositoryRegistry\Factory\AuthProvider\AuthProviderFactoryInterface;
use Neos\ContentRepositoryRegistry\Factory\Clock\ClockFactoryInterface;
use Neos\ContentRepositoryRegistry\Factory\ContentDimensionSource\ContentDimensionSourceFactoryInterface;
use Neos\ContentRepositoryRegistry\Factory\EventStore\EventStoreFactoryInterface;
use Neos\ContentRepositoryRegistry\Factory\NodeTypeManager\NodeTypeManagerFactoryInterface;
use Neos\ContentRepositoryRegistry\Factory\SubscriptionStore\SubscriptionStoreFactoryInterface;
use Neos\ContentRepositoryRegistry\SubgraphCachingInMemory\ContentSubgraphWithRuntimeCaches;
use Neos\ContentRepositoryRegistry\SubgraphCachingInMemory\SubgraphCachePool;
use Neos\EventStore\EventStoreInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Utility\Arrays;
use Neos\Utility\PositionalArraySorter;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Serializer;

/**
 * @api
 */
#[Flow\Scope('singleton')]
final class ContentRepositoryRegistry
{
    /**
     * @var array<string, ContentRepositoryFactory>
     */
    private array $factoryInstances = [];

    /**
     * @var array<string, mixed>
     */
    private array $settings;

    #[Flow\Inject(name: 'Neos.ContentRepositoryRegistry:Logger', lazy: false)]
    protected LoggerInterface $logger;

    #[Flow\Inject()]
    protected ObjectManagerInterface $objectManager;

    #[Flow\Inject()]
    protected SubgraphCachePool $subgraphCachePool;

    /**
     * @internal for flow wiring and test cases only
     * @param array<string, mixed> $settings
     */
    public function injectSettings(array $settings): void
    {
        $this->settings = $settings;
    }

    /**
     * This is the main entry point for Neos / Flow installations to fetch a content repository.
     * A content repository is not a singleton and must be fetched by its identifier.
     *
     * To get a hold of a content repository identifier, it has to be passed along.
     *
     * For Neos web requests, the current content repository can be inferred by the domain and the connected site:
     * {@see \Neos\Neos\FrontendRouting\SiteDetection\SiteDetectionResult::fromRequest()}
     * Or it has to be encoded manually as part of a query parameter.
     *
     * For CLI applications, it's a necessity to specify the content repository as argument from the outside,
     * generally via `--content-repository default`
     *
     * The content repository identifier should never be hard-coded without being aware of its implications.
     *
     * Hint: in case you are already in a service that is scoped to a content repository or a projection catchup hook,
     * the content repository will likely be already available via e.g. the service factory.
     *
     * @throws ContentRepositoryNotFoundException | InvalidConfigurationException
     */
    public function get(ContentRepositoryId $contentRepositoryId): ContentRepository
    {
        return $this->getFactory($contentRepositoryId)->getOrBuild();
    }

    public function getContentRepositoryIds(): ContentRepositoryIds
    {
        /** @var array<string> $contentRepositoryIds */
        $contentRepositoryIds = array_keys($this->settings['contentRepositories'] ?? []);
        return ContentRepositoryIds::fromArray($contentRepositoryIds);
    }

    public function subgraphForNode(Node $node): ContentSubgraphInterface
    {
        $contentRepository = $this->get($node->contentRepositoryId);

        $uncachedSubgraph = $contentRepository->getContentGraph($node->workspaceName)->getSubgraph(
            $node->dimensionSpacePoint,
            $node->visibilityConstraints
        );

        return new ContentSubgraphWithRuntimeCaches($uncachedSubgraph, $this->subgraphCachePool);
    }

    /**
     * Access content repository services.
     *
     * The services are a low level extension mechanism and only few are part of the public API.
     *
     * @param ContentRepositoryId $contentRepositoryId
     * @param ContentRepositoryServiceFactoryInterface<T> $contentRepositoryServiceFactory
     * @return T
     * @throws ContentRepositoryNotFoundException | InvalidConfigurationException
     * @template T of ContentRepositoryServiceInterface
     */
    public function buildService(ContentRepositoryId $contentRepositoryId, ContentRepositoryServiceFactoryInterface $contentRepositoryServiceFactory): ContentRepositoryServiceInterface
    {
        return $this->getFactory($contentRepositoryId)->buildService($contentRepositoryServiceFactory);
    }

    /**
     * @internal for test cases only
     */
    public function resetFactoryInstance(ContentRepositoryId $contentRepositoryId): void
    {
        if (array_key_exists($contentRepositoryId->value, $this->factoryInstances)) {
            unset($this->factoryInstances[$contentRepositoryId->value]);
        }
    }

    /**
     * @throws ContentRepositoryNotFoundException | InvalidConfigurationException
     */
    private function getFactory(
        ContentRepositoryId $contentRepositoryId
    ): ContentRepositoryFactory {
        // This cache is CRUCIAL, because it ensures that the same CR always deals with the same objects internally, even if multiple services
        // are called on the same CR.
        if (!array_key_exists($contentRepositoryId->value, $this->factoryInstances)) {
            $this->factoryInstances[$contentRepositoryId->value] = $this->buildFactory($contentRepositoryId);
        }
        return $this->factoryInstances[$contentRepositoryId->value];
    }

    /**
     * @throws ContentRepositoryNotFoundException | InvalidConfigurationException
     */
    private function buildFactory(ContentRepositoryId $contentRepositoryId): ContentRepositoryFactory
    {
        if (!is_array($this->settings['contentRepositories'] ?? null)) {
            throw InvalidConfigurationException::fromMessage('No Content Repositories are configured');
        }

        if (!isset($this->settings['contentRepositories'][$contentRepositoryId->value]) || !is_array($this->settings['contentRepositories'][$contentRepositoryId->value])) {
            throw ContentRepositoryNotFoundException::notConfigured($contentRepositoryId);
        }
        $contentRepositorySettings = $this->settings['contentRepositories'][$contentRepositoryId->value];
        if (isset($contentRepositorySettings['preset'])) {
            is_string($contentRepositorySettings['preset']) || throw InvalidConfigurationException::fromMessage('Invalid "preset" configuration for Content Repository "%s". Expected string, got: %s', $contentRepositoryId->value, get_debug_type($contentRepositorySettings['preset']));
            if (!isset($this->settings['presets'][$contentRepositorySettings['preset']]) || !is_array($this->settings['presets'][$contentRepositorySettings['preset']])) {
                throw InvalidConfigurationException::fromMessage('Content Repository settings "%s" refer to a preset "%s", but there are not presets configured', $contentRepositoryId->value, $contentRepositorySettings['preset']);
            }
            $contentRepositorySettings = Arrays::arrayMergeRecursiveOverrule($this->settings['presets'][$contentRepositorySettings['preset']], $contentRepositorySettings);
            unset($contentRepositorySettings['preset']);
        }
        try {
            /** @var CatchUpHookFactoryInterface<ContentGraphReadModelInterface>|null $contentGraphCatchUpHookFactory */
            $contentGraphCatchUpHookFactory = $this->buildCatchUpHookFactory($contentRepositoryId, 'contentGraph', $contentRepositorySettings['contentGraphProjection']);
            $clock = $this->buildClock($contentRepositoryId, $contentRepositorySettings);
            return new ContentRepositoryFactory(
                $contentRepositoryId,
                $this->buildEventStore($contentRepositoryId, $contentRepositorySettings, $clock),
                $this->buildNodeTypeManager($contentRepositoryId, $contentRepositorySettings),
                $this->buildContentDimensionSource($contentRepositoryId, $contentRepositorySettings),
                $this->buildPropertySerializer($contentRepositoryId, $contentRepositorySettings),
                $this->buildAuthProviderFactory($contentRepositoryId, $contentRepositorySettings),
                $clock,
                $this->buildSubscriptionStore($contentRepositoryId, $clock, $contentRepositorySettings),
                $this->buildContentGraphProjectionFactory($contentRepositoryId, $contentRepositorySettings),
                $contentGraphCatchUpHookFactory,
                $this->buildCommandHooksFactory($contentRepositoryId, $contentRepositorySettings),
                $this->buildAdditionalSubscribersFactories($contentRepositoryId, $contentRepositorySettings),
                $this->logger,
            );
        } catch (\Exception $exception) {
            throw InvalidConfigurationException::fromException($contentRepositoryId, $exception);
        }
    }

    /** @param array<string, mixed> $contentRepositorySettings */
    private function buildEventStore(ContentRepositoryId $contentRepositoryId, array $contentRepositorySettings, ClockInterface $clock): EventStoreInterface
    {
        isset($contentRepositorySettings['eventStore']['factoryObjectName']) || throw InvalidConfigurationException::fromMessage('Content repository "%s" does not have eventStore.factoryObjectName configured.', $contentRepositoryId->value);
        $eventStoreFactory = $this->objectManager->get($contentRepositorySettings['eventStore']['factoryObjectName']);
        if (!$eventStoreFactory instanceof EventStoreFactoryInterface) {
            throw InvalidConfigurationException::fromMessage('eventStore.factoryObjectName for content repository "%s" is not an instance of %s but %s.', $contentRepositoryId->value, EventStoreFactoryInterface::class, get_debug_type($eventStoreFactory));
        }
        return $eventStoreFactory->build($contentRepositoryId, $contentRepositorySettings['eventStore']['options'] ?? [], $clock);
    }

    /** @param array<string, mixed> $contentRepositorySettings */
    private function buildNodeTypeManager(ContentRepositoryId $contentRepositoryId, array $contentRepositorySettings): NodeTypeManager
    {
        isset($contentRepositorySettings['nodeTypeManager']['factoryObjectName']) || throw InvalidConfigurationException::fromMessage('Content repository "%s" does not have nodeTypeManager.factoryObjectName configured.', $contentRepositoryId->value);
        $nodeTypeManagerFactory = $this->objectManager->get($contentRepositorySettings['nodeTypeManager']['factoryObjectName']);
        if (!$nodeTypeManagerFactory instanceof NodeTypeManagerFactoryInterface) {
            throw InvalidConfigurationException::fromMessage('nodeTypeManager.factoryObjectName for content repository "%s" is not an instance of %s but %s.', $contentRepositoryId->value, NodeTypeManagerFactoryInterface::class, get_debug_type($nodeTypeManagerFactory));
        }
        return $nodeTypeManagerFactory->build($contentRepositoryId, $contentRepositorySettings['nodeTypeManager']['options'] ?? []);
    }

    /** @param array<string, mixed> $contentRepositorySettings */
    private function buildContentDimensionSource(ContentRepositoryId $contentRepositoryId, array $contentRepositorySettings): ContentDimensionSourceInterface
    {
        isset($contentRepositorySettings['contentDimensionSource']['factoryObjectName']) || throw InvalidConfigurationException::fromMessage('Content repository "%s" does not have contentDimensionSource.factoryObjectName configured.', $contentRepositoryId->value);
        $contentDimensionSourceFactory = $this->objectManager->get($contentRepositorySettings['contentDimensionSource']['factoryObjectName']);
        if (!$contentDimensionSourceFactory instanceof ContentDimensionSourceFactoryInterface) {
            throw InvalidConfigurationException::fromMessage('contentDimensionSource.factoryObjectName for content repository "%s" is not an instance of %s but %s.', $contentRepositoryId->value, NodeTypeManagerFactoryInterface::class, get_debug_type($contentDimensionSourceFactory));
        }
        // Note: contentDimensions can be specified on the top-level for easier use.
        // They can still be overridden in the specific "contentDimensionSource" options
        $options = $contentRepositorySettings['contentDimensionSource']['options'] ?? [];
        if (isset($contentRepositorySettings['contentDimensions'])) {
            $options['contentDimensions'] = Arrays::arrayMergeRecursiveOverrule($contentRepositorySettings['contentDimensions'], $options['contentDimensions'] ?? []);
        }
        return $contentDimensionSourceFactory->build($contentRepositoryId, $options);
    }

    /** @param array<string, mixed> $contentRepositorySettings */
    private function buildPropertySerializer(ContentRepositoryId $contentRepositoryId, array $contentRepositorySettings): Serializer
    {
        (isset($contentRepositorySettings['propertyConverters']) && is_array($contentRepositorySettings['propertyConverters'])) || throw InvalidConfigurationException::fromMessage('Content repository "%s" does not have propertyConverters configured, or the value is no array.', $contentRepositoryId->value);
        $propertyConvertersConfiguration = (new PositionalArraySorter($contentRepositorySettings['propertyConverters']))->toArray();

        $normalizers = [];
        foreach ($propertyConvertersConfiguration as $propertyConverterConfiguration) {
            $normalizer = new $propertyConverterConfiguration['className']();
            if (!$normalizer instanceof NormalizerInterface && !$normalizer instanceof DenormalizerInterface) {
                throw InvalidConfigurationException::fromMessage('Serializers can only be created of %s and %s, %s given', NormalizerInterface::class, DenormalizerInterface::class, get_debug_type($normalizer));
            }
            $normalizers[] = $normalizer;
        }
        return new Serializer($normalizers);
    }

    /** @param array<string, mixed> $contentRepositorySettings */
    private function buildContentGraphProjectionFactory(ContentRepositoryId $contentRepositoryId, array $contentRepositorySettings): ContentGraphProjectionFactoryInterface
    {
        if (!isset($contentRepositorySettings['contentGraphProjection']['factoryObjectName'])) {
            throw InvalidConfigurationException::fromMessage('Content repository "%s" does not have the contentGraphProjection.factoryObjectName configured.', $contentRepositoryId->value);
        }

        $contentGraphProjectionFactory = $this->objectManager->get($contentRepositorySettings['contentGraphProjection']['factoryObjectName']);
        if (!$contentGraphProjectionFactory instanceof ContentGraphProjectionFactoryInterface) {
            throw InvalidConfigurationException::fromMessage('Projection factory object name of contentGraphProjection (content repository "%s") is not an instance of %s but %s.', $contentRepositoryId->value, ContentGraphProjectionFactoryInterface::class, get_debug_type($contentGraphProjectionFactory));
        }
        return $contentGraphProjectionFactory;
    }

    /**
     * @param array<string, mixed> $projectionOptions
     * @return CatchUpHookFactoryInterface<ProjectionStateInterface>|null
     */
    private function buildCatchUpHookFactory(ContentRepositoryId $contentRepositoryId, string $projectionName, array $projectionOptions): ?CatchUpHookFactoryInterface
    {
        if (!isset($projectionOptions['catchUpHooks'])) {
            return null;
        }
        $catchUpHookFactories = CatchUpHookFactories::create();
        foreach ($projectionOptions['catchUpHooks'] as $catchUpHookName => $catchUpHookOptions) {
            if ($catchUpHookOptions === null) {
                // Allow catch up hooks to be disabled by setting their configuration to `null`
                continue;
            }
            $catchUpHookFactory = $this->objectManager->get($catchUpHookOptions['factoryObjectName']);
            if (!$catchUpHookFactory instanceof CatchUpHookFactoryInterface) {
                throw InvalidConfigurationException::fromMessage('CatchUpHook factory object name for hook "%s" in projection "%s" (content repository "%s") is not an instance of %s but %s', $catchUpHookName, $projectionName, $contentRepositoryId->value, CatchUpHookFactoryInterface::class, get_debug_type($catchUpHookFactory));
            }
            $catchUpHookFactories = $catchUpHookFactories->with($catchUpHookFactory);
        }
        if ($catchUpHookFactories->isEmpty()) {
            return null;
        }
        return $catchUpHookFactories;
    }

    /** @param array<string, mixed> $contentRepositorySettings */
    private function buildCommandHooksFactory(ContentRepositoryId $contentRepositoryId, array $contentRepositorySettings): CommandHooksFactory
    {
        $commandHooksSettings = $contentRepositorySettings['commandHooks'] ?? [];
        if (!is_array($commandHooksSettings)) {
            throw InvalidConfigurationException::fromMessage('Content repository "%s" does not have the "commandHooks" configured properly. Expected array, got %s.', $contentRepositoryId->value, get_debug_type($commandHooksSettings));
        }
        $commandHookFactories = [];
        foreach ((new PositionalArraySorter($commandHooksSettings))->toArray() as $name => $commandHookSettings) {
            // Allow to unset/disable command hooks
            if ($commandHookSettings === null) {
                continue;
            }
            $commandHookFactory = $this->objectManager->get($commandHookSettings['factoryObjectName']);
            if (!$commandHookFactory instanceof CommandHookFactoryInterface) {
                throw InvalidConfigurationException::fromMessage('Factory object name for command hook "%s" (content repository "%s") is not an instance of %s but %s.', $name, $contentRepositoryId->value, CommandHookFactoryInterface::class, get_debug_type($commandHookFactory));
            }
            $commandHookFactories[] = $commandHookFactory;
        }
        return new CommandHooksFactory(...$commandHookFactories);
    }

    /** @param array<string, mixed> $contentRepositorySettings */
    private function buildAdditionalSubscribersFactories(ContentRepositoryId $contentRepositoryId, array $contentRepositorySettings): ContentRepositorySubscriberFactories
    {
        if (!is_array($contentRepositorySettings['projections'] ?? [])) {
            throw InvalidConfigurationException::fromMessage('Content repository "%s" expects projections configured as array.', $contentRepositoryId->value);
        }
        /** @var array<ProjectionSubscriberFactory> $projectionSubscriberFactories */
        $projectionSubscriberFactories = [];
        foreach (($contentRepositorySettings['projections'] ?? []) as $projectionName => $projectionOptions) {
            // Allow projections to be disabled by setting their configuration to `null`
            if ($projectionOptions === null) {
                continue;
            }
            if (!is_array($projectionOptions)) {
                throw InvalidConfigurationException::fromMessage('Projection "%s" (content repository "%s") must be configured as array got %s', $projectionName, $contentRepositoryId->value, get_debug_type($projectionOptions));
            }
            $projectionFactory = isset($projectionOptions['factoryObjectName']) ? $this->objectManager->get($projectionOptions['factoryObjectName']) : null;
            if (!$projectionFactory instanceof ProjectionFactoryInterface) {
                throw InvalidConfigurationException::fromMessage('Projection factory object name for projection "%s" (content repository "%s") is not an instance of %s but %s.', $projectionName, $contentRepositoryId->value, ProjectionFactoryInterface::class, get_debug_type($projectionFactory));
            }
            $projectionSubscriberFactories[$projectionName] = new ProjectionSubscriberFactory(
                SubscriptionId::fromString($projectionName),
                $projectionFactory,
                $this->buildCatchUpHookFactory($contentRepositoryId, $projectionName, $projectionOptions),
                $projectionOptions['options'] ?? [],
            );
        }
        return ContentRepositorySubscriberFactories::fromArray($projectionSubscriberFactories);
    }

    /** @param array<string, mixed> $contentRepositorySettings */
    private function buildAuthProviderFactory(ContentRepositoryId $contentRepositoryId, array $contentRepositorySettings): AuthProviderFactoryInterface
    {
        isset($contentRepositorySettings['authProvider']['factoryObjectName']) || throw InvalidConfigurationException::fromMessage('Content repository "%s" does not have authProvider.factoryObjectName configured.', $contentRepositoryId->value);
        $authProviderFactory = $this->objectManager->get($contentRepositorySettings['authProvider']['factoryObjectName']);
        if (!$authProviderFactory instanceof AuthProviderFactoryInterface) {
            throw InvalidConfigurationException::fromMessage('authProvider.factoryObjectName for content repository "%s" is not an instance of %s but %s.', $contentRepositoryId->value, AuthProviderFactoryInterface::class, get_debug_type($authProviderFactory));
        }
        return $authProviderFactory;
    }

    /** @param array<string, mixed> $contentRepositorySettings */
    private function buildClock(ContentRepositoryId $contentRepositoryIdentifier, array $contentRepositorySettings): ClockInterface
    {
        isset($contentRepositorySettings['clock']['factoryObjectName']) || throw InvalidConfigurationException::fromMessage('Content repository "%s" does not have clock.factoryObjectName configured.', $contentRepositoryIdentifier->value);
        $clockFactory = $this->objectManager->get($contentRepositorySettings['clock']['factoryObjectName']);
        if (!$clockFactory instanceof ClockFactoryInterface) {
            throw InvalidConfigurationException::fromMessage('clock.factoryObjectName for content repository "%s" is not an instance of %s but %s.', $contentRepositoryIdentifier->value, ClockFactoryInterface::class, get_debug_type($clockFactory));
        }
        return $clockFactory->build($contentRepositoryIdentifier, $contentRepositorySettings['clock']['options'] ?? []);
    }

    /** @param array<string, mixed> $contentRepositorySettings */
    private function buildSubscriptionStore(ContentRepositoryId $contentRepositoryId, ClockInterface $clock, array $contentRepositorySettings): SubscriptionStoreInterface
    {
        isset($contentRepositorySettings['subscriptionStore']['factoryObjectName']) || throw InvalidConfigurationException::fromMessage('Content repository "%s" does not have subscriptionStore.factoryObjectName configured.', $contentRepositoryId->value);
        $subscriptionStoreFactory = $this->objectManager->get($contentRepositorySettings['subscriptionStore']['factoryObjectName']);
        if (!$subscriptionStoreFactory instanceof SubscriptionStoreFactoryInterface) {
            throw InvalidConfigurationException::fromMessage('subscriptionStore.factoryObjectName for content repository "%s" is not an instance of %s but %s.', $contentRepositoryId->value, SubscriptionStoreFactoryInterface::class, get_debug_type($subscriptionStoreFactory));
        }
        return $subscriptionStoreFactory->build($contentRepositoryId, $clock, $contentRepositorySettings['subscriptionStore']['options'] ?? []);
    }
}

