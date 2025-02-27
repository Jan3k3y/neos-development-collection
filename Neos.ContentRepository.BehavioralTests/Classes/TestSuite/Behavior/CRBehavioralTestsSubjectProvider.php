<?php

/*
 * This file is part of the Neos.ContentRepository.TestSuite package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

declare(strict_types=1);

namespace Neos\ContentRepository\BehavioralTests\TestSuite\Behavior;

use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Doctrine\DBAL\Connection;
use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\Service\ContentRepositoryMaintainerFactory;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\Subscription\Engine\SubscriptionEngine;
use Neos\ContentRepository\TestSuite\Behavior\Features\Bootstrap\Helpers\GherkinTableNodeBasedContentDimensionSource;
use Neos\ContentRepository\TestSuite\Fakes\FakeContentDimensionSourceFactory;
use Neos\ContentRepository\TestSuite\Fakes\FakeNodeTypeManagerFactory;
use Neos\EventStore\EventStoreInterface;
use PHPUnit\Framework\Assert;
use Symfony\Component\Yaml\Yaml;

/**
 * Subject provider for behavioral tests
 */
trait CRBehavioralTestsSubjectProvider
{
    /**
     * @var array<string,ContentRepository>
     */
    protected array $contentRepositories = [];

    /**
     * A runtime cache of all content repositories already set up, represented by their ID
     * @var array<ContentRepositoryId>
     */
    protected static array $alreadySetUpContentRepositories = [];

    protected ?ContentRepository $currentContentRepository = null;

    /**
     * @throws \DomainException if the requested content repository instance does not exist
     */
    protected function getContentRepository(ContentRepositoryId $contentRepositoryId): ContentRepository
    {
        if (!array_key_exists($contentRepositoryId->value, $this->contentRepositories)) {
            throw new \DomainException('undeclared content repository ' . $contentRepositoryId->value);
        }

        return $this->contentRepositories[$contentRepositoryId->value];
    }

    /**
     * @Given /^using no content dimensions$/
     */
    public function usingNoContentDimensions(): void
    {
        FakeContentDimensionSourceFactory::setWithoutDimensions();
    }

    /**
     * @Given /^using the following content dimensions:$/
     */
    public function usingTheFollowingContentDimensions(TableNode $contentDimensions): void
    {
        FakeContentDimensionSourceFactory::setContentDimensionSource(
            GherkinTableNodeBasedContentDimensionSource::fromGherkinTableNode($contentDimensions)
        );
    }

    /**
     * @Given /^using the following node types:$/
     */
    public function usingTheFollowingNodeTypes(PyStringNode $serializedNodeTypesConfiguration): void
    {
        FakeNodeTypeManagerFactory::setConfiguration(Yaml::parse($serializedNodeTypesConfiguration->getRaw()) ?? []);
    }

    /**
     * @Given /^using identifier "([^"]*)", I define a content repository$/
     */
    public function usingIdentifierIDefineAContentRepository(string $contentRepositoryId): void
    {
        if (array_key_exists($contentRepositoryId, $this->contentRepositories)) {
            throw new \DomainException('already defined content repository ' . $contentRepositoryId);
        } else {
            $this->contentRepositories[$contentRepositoryId] = $this->setUpContentRepository(ContentRepositoryId::fromString($contentRepositoryId));
        }
    }

    /**
     * @Given /^I change the content dimensions in content repository "([^"]*)" to:$/
     */
    public function iChangeTheContentDimensionsInContentRepositoryTo(string $contentRepositoryId, TableNode $contentDimensions): void
    {
        if (!array_key_exists($contentRepositoryId, $this->contentRepositories)) {
            throw new \DomainException('undeclared content repository ' . $contentRepositoryId);
        } else {
            $contentRepository = $this->contentRepositories[$contentRepositoryId];
            // ensure that the current node types of exactly THE content repository are preserved
            FakeNodeTypeManagerFactory::setNodeTypeManager($contentRepository->getNodeTypeManager());
            FakeContentDimensionSourceFactory::setContentDimensionSource(
                GherkinTableNodeBasedContentDimensionSource::fromGherkinTableNode($contentDimensions)
            );
            $this->contentRepositories[$contentRepositoryId] = $this->createContentRepository(ContentRepositoryId::fromString($contentRepositoryId));
            if ($this->currentContentRepository->id->value === $contentRepositoryId) {
                $this->currentContentRepository = $this->contentRepositories[$contentRepositoryId];
            }
        }
    }

    /**
     * @Given /^I change the node types in content repository "([^"]*)" to:$/
     */
    public function iChangeTheNodeTypesInContentRepositoryTo(
        string $contentRepositoryId,
        PyStringNode $serializedNodeTypesConfiguration
    ): void {
        if (!array_key_exists($contentRepositoryId, $this->contentRepositories)) {
            throw new \DomainException('undeclared content repository ' . $contentRepositoryId);
        } else {
            $contentRepository = $this->contentRepositories[$contentRepositoryId];
            // ensure that the current node types of exactly THE content repository are preserved
            FakeContentDimensionSourceFactory::setContentDimensionSource($contentRepository->getContentDimensionSource());
            FakeNodeTypeManagerFactory::setConfiguration(Yaml::parse($serializedNodeTypesConfiguration->getRaw()) ?? []);
            $this->contentRepositories[$contentRepositoryId] = $this->createContentRepository(ContentRepositoryId::fromString($contentRepositoryId));
            if ($this->currentContentRepository->id->value === $contentRepositoryId) {
                $this->currentContentRepository = $this->contentRepositories[$contentRepositoryId];
            }
        }
    }

    protected function setUpContentRepository(ContentRepositoryId $contentRepositoryId): ContentRepository
    {
        /**
         * Reset events and projections
         * ============================
         *
         * PITFALL: for a long time, the code below was a two-liner (it is not anymore, for reasons explained here):
         * - reset projections (truncate table contents)
         * - truncate events table.
         *
         * This code has SERIOUS Race Condition and Bug Potential.
         * tl;dr: It is CRUCIAL that *FIRST* the event store is emptied, and *then* the projection state is reset;
         * so the OPPOSITE order as described above.
         *
         * If doing it in the way described initially, the following can happen (time flows from top to bottom):
         *
         * ```
         * Main Behat Process                        Dangling Projection catch up worker
         * ==================                        ===================================
         *
         *                                           (hasn't started working yet, simply sleeping)
         *
         * 1) Projection State reset
         *                                           "oh, I have some work to do to catch up EVERYTHING"
         *                                           "query the events table"
         *
         * 2) Event Table Reset
         *                                           (events table is already loaded into memory) -> replay WIP
         *
         * (new commands/events start happening,
         * in the new testcase)
         *                                           ==> ERRORS because the projection now contains the result of both
         *                                               old AND new events (of the two different testcases) <==
         * ```
         *
         * This was an actual bug which bit us and made our tests unstable :D :D
         *
         * How did we find this? By the virtue of our Race Tracker (Docs: see {@see RaceTrackerCatchUpHook}), which
         * checks for events being applied multiple times to a projection.
         * ... and additionally by using {@see logToRaceConditionTracker()} to find the interleavings between the
         * Catch Up process and the testcase reset.
         */
        $contentRepository = $this->createContentRepository($contentRepositoryId);
        $contentRepositoryMaintainer = $this->contentRepositoryRegistry->buildService($contentRepositoryId, new ContentRepositoryMaintainerFactory());
        if (!in_array($contentRepository->id, self::$alreadySetUpContentRepositories)) {
            $result = $contentRepositoryMaintainer->setUp();
            Assert::assertNull($result);
            self::$alreadySetUpContentRepositories[] = $contentRepository->id;
        }
        // todo we TRUNCATE here and do not want to use $contentRepositoryMaintainer->prune(); here as it would not reset the autoincrement sequence number making some assertions impossible
        /** @var EventStoreInterface $eventStore */
        $eventStore = (new \ReflectionClass($contentRepository))->getProperty('eventStore')->getValue($contentRepository);
        /** @var Connection $databaseConnection */
        $databaseConnection = (new \ReflectionClass($eventStore))->getProperty('connection')->getValue($eventStore);
        $eventTableName = sprintf('cr_%s_events', $contentRepositoryId->value);
        $databaseConnection->executeStatement('TRUNCATE ' . $eventTableName);

        /** @var SubscriptionEngine $subscriptionEngine */
        $subscriptionEngine = (new \ReflectionClass($contentRepositoryMaintainer))->getProperty('subscriptionEngine')->getValue($contentRepositoryMaintainer);
        $result = $subscriptionEngine->reset();
        Assert::assertNull($result->errors);
        $result = $subscriptionEngine->boot();
        Assert::assertNull($result->errors);

        return $contentRepository;
    }

    abstract protected function createContentRepository(ContentRepositoryId $contentRepositoryId): ContentRepository;
}
