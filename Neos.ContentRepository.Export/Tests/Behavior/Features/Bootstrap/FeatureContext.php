<?php
declare(strict_types=1);

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

require_once(__DIR__ . '/CrImportExportTrait.php');

use Behat\Behat\Context\Context as BehatContext;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Neos\Behat\FlowBootstrapTrait;
use Neos\ContentGraph\DoctrineDbalAdapter\Tests\Behavior\Features\Bootstrap\CrImportExportTrait;
use Neos\ContentRepository\BehavioralTests\TestSuite\Behavior\CRBehavioralTestsSubjectProvider;
use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\Factory\ContentRepositoryServiceFactoryInterface;
use Neos\ContentRepository\Core\Factory\ContentRepositoryServiceInterface;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\TestSuite\Behavior\Features\Bootstrap\CRTestSuiteTrait;
use Neos\ContentRepository\TestSuite\Fakes\FakeNodeTypeManagerFactory;
use Neos\ContentRepository\TestSuite\Fakes\FakeContentDimensionSourceFactory;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;

/**
 * Features context
 */
class FeatureContext implements BehatContext
{
    use FlowBootstrapTrait;
    use CrImportExportTrait;
    use CRTestSuiteTrait;
    use CRBehavioralTestsSubjectProvider;

    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    public function __construct()
    {
        self::bootstrapFlow();
        $this->contentRepositoryRegistry = $this->getObject(ContentRepositoryRegistry::class);

        $this->setupCrImportExportTrait();
    }

    /**
     * @BeforeScenario
     */
    public function resetContentRepositoryComponents(BeforeScenarioScope $scope): void
    {
        FakeContentDimensionSourceFactory::reset();
        FakeNodeTypeManagerFactory::reset();
    }

    protected function getContentRepositoryService(
        ContentRepositoryServiceFactoryInterface $factory
    ): ContentRepositoryServiceInterface {
        return $this->contentRepositoryRegistry->buildService(
            $this->currentContentRepository->id,
            $factory
        );
    }

    protected function createContentRepository(
        ContentRepositoryId $contentRepositoryId
    ): ContentRepository {
        $this->contentRepositoryRegistry->resetFactoryInstance($contentRepositoryId);
        $contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryId);
        FakeContentDimensionSourceFactory::reset();
        FakeNodeTypeManagerFactory::reset();

        return $contentRepository;
    }
}
