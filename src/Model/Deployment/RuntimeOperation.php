<?php

declare(strict_types=1);

namespace Platformsh\Client\Model\Deployment;

use Platformsh\Client\DataStructure\ReadOnlyStructureTrait;

/**
 * A runtime operation in an application.
 *
 * @property-read array{'start': string, 'stop': ?string} $commands
 * @property-read mixed $timeout
 * @property-read string $role
 */
class RuntimeOperation
{
    use ReadOnlyStructureTrait;
}
