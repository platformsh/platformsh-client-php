<?php

declare(strict_types=1);

namespace Platformsh\Client\Model\CentralizedPermissions;

use GuzzleHttp\ClientInterface;
use Platformsh\Client\Model\Ref\OrganizationRef;
use Platformsh\Client\Model\Ref\ProjectRef;
use Platformsh\Client\Model\ResourceWithReferences;

/**
 * A document representing a user's extended access to multiple resources.
 *
 * "Extended" access means that it might be access granted directly and/or via team memberships.
 *
 * @property-read string $user_id
 * @property-read string $organization_id
 * @property-read string $resource_id
 * @property-read string $resource_type
 * @property-read string[] $permissions
 * @property-read string $granted_at
 * @property-read string $updated_at
 */
class UserExtendedAccess extends ResourceWithReferences
{
    /**
     * @return static[]
     */
    public static function byUser(string $userId, array $options, ClientInterface $client): array
    {
        return self::getCollection('/users/' . rawurlencode($userId) . '/extended-access', 0, $options, $client);
    }

    public function getProjectInfo(): ?ProjectRef
    {
        if (isset($this->data['ref:projects'][$this->data['resource_id']])) {
            return $this->data['ref:projects'][$this->data['resource_id']];
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
