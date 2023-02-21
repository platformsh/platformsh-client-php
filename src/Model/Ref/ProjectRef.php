<?php

namespace Platformsh\Client\Model\Ref;

use Platformsh\Client\DataStructure\ReadOnlyStructureTrait;

/**
 * @property-read string $id
 * @property-read string $organization_id
 * @property-read string $subscription_id
 * @property-read string $region
 * @property-read string $title
 * @property-read string $status
 * @property-read string $created_at
 * @property-read string $updated_at
 */
class ProjectRef
{
    use ReadOnlyStructureTrait;
}
