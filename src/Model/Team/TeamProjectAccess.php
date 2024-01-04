<?php

namespace Platformsh\Client\Model\Team;

use Platformsh\Client\Model\Resource;

/**
 * @property-read string $team_id
 * @property-read string $organization_id
 * @property-read string $project_id
 * @property-read string $project_title
 * @property-read string $granted_at
 * @property-read string $updated_at
 */
class TeamProjectAccess extends Resource
{
    protected static $collectionItemsKey = 'items';
}
