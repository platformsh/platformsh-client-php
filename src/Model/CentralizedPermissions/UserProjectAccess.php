<?php

namespace Platformsh\Client\Model\CentralizedPermissions;

use GuzzleHttp\ClientInterface;
use Platformsh\Client\Model\Ref\OrganizationRef;
use Platformsh\Client\Model\Ref\ProjectRef;
use Platformsh\Client\Model\ResourceWithReferences;

/**
 * A document representing a user's access to multiple Platform.sh projects.
 *
 * @property-read string $user_id
 * @property-read string $organization_id
 * @property-read string $project_id
 * @property-read string[] $permissions
 * @property-read string $granted_at
 * @property-read string $updated_at
 */
class UserProjectAccess extends ResourceWithReferences
{
    /**
     * @param string $userId
     * @param array $options
     * @param ClientInterface $client
     *
     * @return static[]
     */
    public static function byUser($userId, array $options, ClientInterface $client)
    {
        return UserProjectAccess::getCollection('/users/' . rawurlencode($userId) . '/project-access', 0, $options, $client);
    }

    /** @return ProjectRef|null */
    public function getProjectInfo()
    {
        if (isset($this->data['ref:projects'][$this->data['project_id']])) {
            return $this->data['ref:projects'][$this->data['project_id']];
        }
        return null;
    }

    /** @return OrganizationRef|null */
    public function getOrganizationInfo()
    {
        if (isset($this->data['ref:organizations'][$this->data['organization_id']])) {
            return $this->data['ref:organizations'][$this->data['organization_id']];
        }
        return null;
    }
}
