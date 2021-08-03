<?php

namespace Platformsh\Client\Model\Organization\Invitation;

use Platformsh\Client\Model\Resource;

/**
 * @property-read string $id
 * @property-read string $state
 * @property-read string[] $permissions
 * @property-read string $created_at
 * @property-read string $updated_at
 * @property-read string|null $finished_at
 */
class OrganizationInvitation extends Resource {}
