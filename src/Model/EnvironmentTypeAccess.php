<?php

namespace Platformsh\Client\Model;

/**
 * A record establishing a user's access to an environment type.
 *
 * @property-read string $user The user ID
 * @property-read string $role The user's role on all environments of this type
 */
class EnvironmentTypeAccess extends ApiResourceBase
{
    protected static $required = ['role'];

    const ROLE_ADMIN = 'admin';
    const ROLE_VIEWER = 'viewer';
    const ROLE_CONTRIBUTOR = 'contributor';

    public function isOperationAvailable($op)
    {
        if ($op === 'edit') {
            // TODO remove this when the API exposes #edit links for /environment-types/ID/access when centralized_permissions are enabled
            return true;
        }
        return parent::isOperationAvailable($op);
    }

    /**
     * {@inheritdoc}
     */
    public function getLink($rel, $absolute = true)
    {
        if ($rel === "#edit" && !$this->hasLink($rel)) {
            return $this->getUri($absolute);
        }

        return parent::getLink($rel, $absolute);
    }
}
