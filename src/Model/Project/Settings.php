<?php

namespace Platformsh\Client\Model\Project;

use Platformsh\Client\Model\Resource;

/**
 * Settings represent various flags on a project.
 *
 * Many of them can only be changed by support staff or internal systems.
 *
 * See https://api.platform.sh/docs/#tag/Project-Settings/operation/get-projects-settings
 *
 * @property-read array{cpu: int, memory: int} $build_resources
 */
class Settings extends Resource
{
    public function delete()
    {
        throw new \BadMethodCallException('Settings cannot be deleted');
    }
}
