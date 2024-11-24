<?php

namespace Platformsh\Client\Model\Ref;

use Platformsh\Client\DataStructure\ReadOnlyStructureTrait;

/**
 * @property-read string $id
 * @property-read string $organization_id
 * @property-read string $label
 * @property-read string[] $project_permissions
 * @property-read array{member_count: int, project_count: int} $counts
 * @property-read string $created_at
 * @property-read string $updated_at
 */
class TeamRef
{
    use ReadOnlyStructureTrait;
}
