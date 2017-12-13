<?php

namespace Platformsh\Client\Model\Deployment;

use Platformsh\Client\DataStructure\ReadOnlyStructureTrait;

/**
 * A route in a deployed environment.
 *
 * @property-read string $type
 * @property-read array  $redirects
 * @property-read array  $tls
 * @property-read string $original_url
 */
class Route
{
    use ReadOnlyStructureTrait;
}
