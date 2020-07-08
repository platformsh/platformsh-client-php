<?php

namespace Platformsh\Client\Model\Invitation;

use Platformsh\Client\Model\ApiResourceBase;

/**
 * @property-read string $id
 * @property-read string $state
 * @property-read string $role
 * @property-read Environment[] $environments
 * @property-read string $created_at
 * @property-read string $updated_at
 * @property-read string|null $finished_at
 */
class ProjectInvitation extends ApiResourceBase {
    /**
     * {@inheritDoc}
     *
     * Returns environments as the correct object type.
     */
    public function getProperty($property, $required = true, $lazyLoad = true)
    {
        $value = parent::getProperty($property, $required, $lazyLoad);
        if ($property === 'environments') {
            $environments = [];
            foreach ($value as $item) {
                $environments[] = new Environment($item['id'], $item['role']);
            }
            return $environments;
        }
        return $value;
    }
}
