<?php

declare(strict_types=1);

namespace Platformsh\Client\Model\Organization;

use Platformsh\Client\Model\Ref\UserRef;
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
class Member extends ResourceWithReferences
{
    public function getUserInfo(): ?UserRef
    {
        if (isset($this->data['ref:users'][$this->data['user_id']])) {
            return $this->data['ref:users'][$this->data['user_id']];
        }
        return null;
    }

    public function getLink(string $rel, bool $absolute = true): string
    {
        if ($rel === '#edit') {
            return $this->getLink('self');
        }
        return parent::getLink($rel, $absolute);
    }

    protected function isOperationAvailable(string $op): bool
    {
        if ($op === 'edit') {
            return true;
        }
        return parent::isOperationAvailable($op);
    }
}
