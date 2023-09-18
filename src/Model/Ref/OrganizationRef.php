<?php

namespace Platformsh\Client\Model\Ref;

use Platformsh\Client\DataStructure\ReadOnlyStructureTrait;

/**
 * @property-read string $id
 * @property-read string $owner_id
 * @property-read string $name
 * @property-read string $label
 * @property-read string $vendor
 * @property-read string $created_at
 * @property-read string $updated_at
 */
class OrganizationRef
{
    use ReadOnlyStructureTrait;
}
