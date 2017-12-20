<?php

namespace Platformsh\Client\Model\Deployment;

use Platformsh\Client\DataStructure\ReadOnlyStructureTrait;

/**
 * An application.
 *
 * @property-read string $name
 * @property-read string $type
 * @property-read array  $runtime
 * @property-read array  $preflight
 */
abstract class AppBase
{
    use ReadOnlyStructureTrait;
}
