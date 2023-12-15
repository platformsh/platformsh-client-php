<?php

namespace Platformsh\Client\Model\UserAccess;

use Platformsh\Client\Model\Ref\UserRef;
use Platformsh\Client\Model\ResourceWithReferences;
use Platformsh\Client\Model\Result;

/**
 * @property-read string $user_id
 * @property-read string $organization_id
 * @property-read string $project_id
 * @property-read string[] $permissions
 * @property-read string $granted_at
 * @property-read string $updated_at
 */
class ProjectUserAccess extends ResourceWithReferences
{
    /**
     * @return UserRef
     */
    public function getUserInfo()
    {
        return $this->data['ref:users'][$this->user_id];
    }

    /**
     * @return string
     */
    public function getProjectRole()
    {
        if (in_array('admin', $this->data['permissions'])) {
            return 'admin';
        }
        return 'viewer';
    }

    /**
     * @return array<string, string>
     */
    public function getEnvironmentTypeRoles()
    {
        $roles = [];
        foreach ($this->data['permissions'] as $permission) {
            if (strpos($permission, ':') !== false) {
                list($type, $role) = explode(':', $permission, 2);
                $roles[$type] = $role;
            }
        }
        return $roles;
    }

    public function update(array $values)
    {
        // A successful PATCH on this resource returns an empty 204 result.
        $this->client->patch($this->getLink('update'), ['json' => $values]);

        // TODO this may not be exactly the right merge semantics in general, but it works in this case as this resource only has one writable key
        $this->data = array_replace_recursive($this->data, $values);

        // TODO this method should probably be deprecated as a Result is not very useful here
        $resultData = [];
        $resultData['_embedded']['entity'] = $this->data;

        return new Result($resultData, $this->baseUrl, $this->client, static::class);
    }
}
