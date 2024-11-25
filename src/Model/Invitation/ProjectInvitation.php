<?php

declare(strict_types=1);

namespace Platformsh\Client\Model\Invitation;

use Platformsh\Client\Model\ApiResourceBase;

/**
 * @property-read string $id
 * @property-read string $state
 * @property-read string $role
 * @property-read Environment[] $environments
 * @property-read Permission[] $permissions
 * @property-read string $created_at
 * @property-read string $updated_at
 * @property-read string|null $finished_at
 */
class ProjectInvitation extends ApiResourceBase
{
    /**
     * {@inheritDoc}
     *
     * Returns environments as the correct object type.
     */
    public function getProperty(string $property, bool $required = true, bool $lazyLoad = true): mixed
    {
        $value = parent::getProperty($property, $required, $lazyLoad);
        if ($property === 'environments') {
            $environments = [];
            foreach ($value as $item) {
                $environments[] = new Environment($item['id'], $item['role']);
            }
            return $environments;
        }

        if ($property === 'permissions') {
            $permissions = [];
            foreach ($value as $item) {
                $permissions[] = new Permission($item['type'], $item['role']);
            }
            return $permissions;
        }

        return $value;
    }
}
