<?php
declare(strict_types=1);

namespace Neos\ContentRepositoryRegistry\Processors;

use Neos\ContentRepository\Core\Service\SubscriptionService;
use Neos\ContentRepository\Export\ProcessingContext;
use Neos\ContentRepository\Export\ProcessorInterface;

/**
 * @internal
 */
final readonly class ProjectionResetProcessor implements ProcessorInterface
{
    public function __construct(
        private SubscriptionService $subscriptionService,
    ) {
    }

    public function run(ProcessingContext $context): void
    {
        // TODO implement $this->subscriptionService->reset();
    }
}
