<?php

declare(strict_types=1);

namespace Platformsh\Client\Model\Deployment;

use Platformsh\Client\DataStructure\ReadOnlyStructureTrait;

/**
 * A task in a deployed environment.
 *
 * Tasks are run-to-completion containers. Unlike apps and workers they have no
 * persistent disk and no instance count; only their profile size (CPU & memory)
 * is configurable.
 *
 * @property-read string $name
 * @property-read string $type
 * @property-read string $container_profile
 * @property-read array  $resources
 * @property-read array  $source
 * @property-read array  $hooks
 * @property-read array  $run
 * @property-read array  $mounts
 * @property-read array  $relationships
 * @property-read array  $variables
 * @property-read string|null $timezone
 */
class Task
{
    use ReadOnlyStructureTrait;
}
