<?php

namespace Platformsh\Client\Model\Ref;

use Platformsh\Client\DataStructure\ReadOnlyStructureTrait;

/**
 * @property-read string $id
 * @property-read string $username
 * @property-read string $email
 * @property-read string $first_name
 * @property-read string $last_name
 * @property-read string $picture
 * @property-read string $created_at
 * @property-read string $updated_at
 */
class UserRef
{
    use ReadOnlyStructureTrait;
}
