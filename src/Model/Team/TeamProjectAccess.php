<?php

declare(strict_types=1);

namespace Platformsh\Client\Model\Team;

use Platformsh\Client\Model\ApiResourceBase;

/**
 * @property-read string $team_id
 * @property-read string $organization_id
 * @property-read string $project_id
 * @property-read string $project_title
 * @property-read string $granted_at
 * @property-read string $updated_at
 */
class TeamProjectAccess extends ApiResourceBase
{
    protected static ?string $collectionItemsKey = 'items';
}
