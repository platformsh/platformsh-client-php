<?php

declare(strict_types=1);

namespace Platformsh\Client\Model\Project;

use Platformsh\Client\Model\ApiResourceBase;
use Platformsh\Client\Model\Result;

/**
 * Settings represent various flags on a project.
 *
 * Many of them can only be changed by support staff or internal systems.
 *
 * See https://api.platform.sh/docs/#tag/Project-Settings/operation/get-projects-settings
 *
 * @property-read array{cpu: int, memory: int} $build_resources
 */
class Settings extends ApiResourceBase
{
    public function delete(): Result
    {
        throw new \BadMethodCallException('Settings cannot be deleted');
    }
}
