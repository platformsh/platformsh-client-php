<?php

namespace Platformsh\Client\Model\Deployment;

use Platformsh\Client\DataStructure\ReadOnlyStructureTrait;

/**
 * An application.
 *
 * @property-read string      $name
 * @property-read string      $type
 *
 * @property-read array       $access
 * @property-read int         $disk
 * @property-read array       $mounts
 * @property-read array       $preflight
 * @property-read array       $relationships
 * @property-read array       $runtime
 * @property-read string      $size
 * @property-read string|null $timezone
 * @property-read array       $variables
 */
abstract class AppBase
{
    use ReadOnlyStructureTrait;
}
