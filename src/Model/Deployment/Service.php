<?php

namespace Platformsh\Client\Model\Deployment;

use Platformsh\Client\DataStructure\ReadOnlyStructureTrait;

/**
 * A service in a deployed environment.
 *
 * @property-read string $type
 * @property-read string $size
 * @property-read string $disk
 * @property-read array  $access
 * @property-read array  $configuration
 * @property-read array  $relationships
 */
class Service
{
    use ReadOnlyStructureTrait;
}
