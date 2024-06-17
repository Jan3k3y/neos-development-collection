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

namespace Neos\ContentRepository\Core\Feature\NodeReferencing\Dto;

use Neos\ContentRepository\Core\SharedModel\Node\ReferenceName;

/**
 * Used to denote a referenceName that should be cleared of all references.
 *
 * @api used as part of commands
 */
final readonly class NodeReferenceNameToEmpty
{
    public function __construct(
        public ReferenceName $referenceName,
    ) {
    }
}
