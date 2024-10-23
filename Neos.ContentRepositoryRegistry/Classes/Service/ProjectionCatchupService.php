<?php
declare(strict_types=1);

namespace Neos\ContentRepositoryRegistry\Service;

use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\Factory\ContentRepositoryServiceInterface;
use Neos\ContentRepository\Core\Projection\CatchUpOptions;
use Neos\ContentRepository\Core\Projection\ProjectionInterface;
use Neos\ContentRepository\Core\Projection\Projections;
use Neos\ContentRepository\Core\Projection\ProjectionStateInterface;
use Neos\ContentRepository\Export\ProcessingContext;
use Neos\ContentRepository\Export\ProcessorInterface;
use Neos\EventStore\EventStoreInterface;
use Neos\EventStore\Model\Event\SequenceNumber;
use Neos\EventStore\Model\EventStream\VirtualStreamName;

/**
 * Content Repository service to perform Projection replays
 *
 * @internal this is currently only used by the {@see CrCommandController}
 */
final class ProjectionCatchupService implements ProcessorInterface, ContentRepositoryServiceInterface
{

    public function __construct(
        private readonly Projections $projections,
        private readonly ContentRepository $contentRepository,
        private readonly EventStoreInterface $eventStore,
    ) {
    }

    public function run(ProcessingContext $context): void
    {
        $this->catchupAllProjections(CatchUpOptions::create());
    }

    public function catchupProjection(string $projectionAliasOrClassName, CatchUpOptions $options): void
    {
        $projectionClassName = $this->resolveProjectionClassName($projectionAliasOrClassName);
        $this->contentRepository->catchUpProjection($projectionClassName, $options);
    }

    public function catchupAllProjections(CatchUpOptions $options, ?\Closure $progressCallback = null): void
    {
        foreach ($this->projectionClassNamesAndAliases() as $classNamesAndAlias) {
            if ($progressCallback) {
                $progressCallback($classNamesAndAlias['alias']);
            }
            $this->contentRepository->catchUpProjection($classNamesAndAlias['className'], $options);
        }
    }

    /**
     * @return class-string<ProjectionInterface<ProjectionStateInterface>>
     */
    private function resolveProjectionClassName(string $projectionAliasOrClassName): string
    {
        $lowerCaseProjectionName = strtolower($projectionAliasOrClassName);
        $projectionClassNamesAndAliases = $this->projectionClassNamesAndAliases();
        foreach ($projectionClassNamesAndAliases as $classNamesAndAlias) {
            if (strtolower($classNamesAndAlias['className']) === $lowerCaseProjectionName || strtolower($classNamesAndAlias['alias']) === $lowerCaseProjectionName) {
                return $classNamesAndAlias['className'];
            }
        }
        throw new \InvalidArgumentException(sprintf(
            'The projection "%s" is not registered for this Content Repository. The following projection aliases (or fully qualified class names) can be used: %s',
            $projectionAliasOrClassName,
            implode('', array_map(static fn (array $classNamesAndAlias) => sprintf(chr(10) . ' * %s (%s)', $classNamesAndAlias['alias'], $classNamesAndAlias['className']), $projectionClassNamesAndAliases))
        ), 1680519624);
    }

    /**
     * @return array<array{className: class-string<ProjectionInterface<ProjectionStateInterface>>, alias: string}>
     */
    private function projectionClassNamesAndAliases(): array
    {
        return array_map(
            static fn (string $projectionClassName) => [
                'className' => $projectionClassName,
                'alias' => self::projectionAlias($projectionClassName),
            ],
            $this->projections->getClassNames()
        );
    }

    private static function projectionAlias(string $className): string
    {
        $alias = lcfirst(substr(strrchr($className, '\\') ?: '\\' . $className, 1));
        if (str_ends_with($alias, 'Projection')) {
            $alias = substr($alias, 0, -10);
        }
        return $alias;
    }
}
