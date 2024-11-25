<?php

declare(strict_types=1);

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
     * @return static[]
     */
    public static function byUser(string $userId, array $options, ClientInterface $client): array
    {
        return self::getCollection('/users/' . rawurlencode($userId) . '/project-access', 0, $options, $client);
    }

    public function getProjectInfo(): ?ProjectRef
    {
        if (isset($this->data['ref:projects'][$this->data['project_id']])) {
            return $this->data['ref:projects'][$this->data['project_id']];
        }
        return null;
    }

    public function getOrganizationInfo(): ?OrganizationRef
    {
        if (isset($this->data['ref:organizations'][$this->data['organization_id']])) {
            return $this->data['ref:organizations'][$this->data['organization_id']];
        }
        return null;
    }
}
