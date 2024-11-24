<?php

namespace Platformsh\Client\Model\Team;

use Platformsh\Client\Model\ResourceWithReferences;

/**
 * Represents team access to a project.
 *
 * See: https://api.platform.sh/docs/#tag/Project-Access/operation/list-project-team-access
 *
 * @property-read string $team_id
 * @property-read string $organization_id
 * @property-read string $granted_at
 */
class ProjectTeamAccess extends ResourceWithReferences
{
    /**
     * @return \Platformsh\Client\Model\Ref\TeamRef|null
     */
    public function getTeamInfo()
    {
        if (isset($this->data['ref:teams'][$this->data['team_id']])) {
            return $this->data['ref:teams'][$this->data['team_id']];
        }
        return null;
    }
}
