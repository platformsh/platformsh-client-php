<?php

namespace Platformsh\Client\Model\Organization;

use Platformsh\Client\Model\ApiResourceBase;

/**
 * Represents a project within an organization.
 *
 * @property-read string $id
 * @property-read string $organization_id
 * @property-read string $subscription_id
 * @property-read string $region
 * @property-read string $title
 * @property-read string $status
 * @property-read string $created_at
 * @property-read string $updated_at
 */
class Project extends ApiResourceBase
{
    protected static $collectionItemsKey = 'items';
}
