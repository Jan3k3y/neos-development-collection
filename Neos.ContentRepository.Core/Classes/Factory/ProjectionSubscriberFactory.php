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

namespace Neos\ContentRepository\Core\Factory;

use Neos\ContentRepository\Core\Projection\ProjectionEventHandler;
use Neos\ContentRepository\Core\Projection\ProjectionFactoryInterface;
use Neos\ContentRepository\Core\Projection\ProjectionInterface;
use Neos\ContentRepository\Core\Projection\ProjectionStateInterface;
use Neos\ContentRepository\Core\Subscription\Subscriber\Subscriber;
use Neos\ContentRepository\Core\Subscription\SubscriptionGroup;
use Neos\ContentRepository\Core\Subscription\SubscriptionId;

/**
 * @internal
 */
final readonly class ProjectionSubscriberFactory
{
    /**
     * @param ProjectionFactoryInterface<ProjectionInterface<ProjectionStateInterface>> $projectionFactory
     * @param array<string, mixed> $projectionFactoryOptions
     */
    public function __construct(
        private SubscriptionId $subscriptionId,
        private ProjectionFactoryInterface $projectionFactory,
        private array $projectionFactoryOptions,
    ) {
    }

    public function build(SubscriberFactoryDependencies $dependencies): Subscriber
    {
        return new Subscriber(
            $this->subscriptionId,
            SubscriptionGroup::fromString('projections'),
            ProjectionEventHandler::create($this->projectionFactory->build($dependencies, $this->projectionFactoryOptions)),
        );
    }
}
