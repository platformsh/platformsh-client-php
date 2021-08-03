<?php

namespace Platformsh\Client\Model\Organization;

use GuzzleHttp\ClientInterface;
use Platformsh\Client\Model\Ref\UserRef;
use Platformsh\Client\Model\Resource;
use Platformsh\Client\Model\ResourceWithReferences;

/**
 * @property-read string $id
 * @property-read string $organization_id
 * @property-read string $user_id
 * @property-read string[] $permissions
 * @property-read bool $owner
 * @property-read string $created_at
 * @property-read string $updated_at
 */
class Member extends ResourceWithReferences {

    /** @return UserRef|null */
    public function getUserInfo()
    {
        if (isset($this->data['ref:users'][$this->data['user_id']])) {
            return $this->data['ref:users'][$this->data['user_id']];
        }
        return null;
    }

    /**
     * {@inheritDoc}
     */
    protected function isOperationAvailable($op)
    {
        if ($op === 'edit') {
            return true;
        }
        return parent::isOperationAvailable($op);
    }

    public function getLink($rel, $absolute = true)
    {
        if ($rel === '#edit') {
            return $this->getLink('self');
        }
        return parent::getLink($rel, $absolute);
    }
}
