<?php

namespace Platformsh\Client\Model\Team;

use Platformsh\Client\Model\Ref\UserRef;
use Platformsh\Client\Model\ResourceWithReferences;

/**
 * @property-read string $team_id
 * @property-read string $user_id
 * @property-read string $created_at
 * @property-read string $updated_at
 */
class TeamMember extends ResourceWithReferences
{
    /**
     * @return UserRef
     */
    public function getUserInfo()
    {
        return $this->data['ref:users'][$this->user_id];
    }
}
