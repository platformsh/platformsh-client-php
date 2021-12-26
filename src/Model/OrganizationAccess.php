<?php

namespace Platformsh\Client\Model;

/**
 * Represents a user with access to a Platform.sh organization.
 *
 * @property-read string $id
 * @property-read string $organization_id
 * @property-read string $user_id
 * @property-read array  $permissions
 * @property-read bool   $owner
 * @property-read string $created_at
 * @property-read string $updated_at
 */
class OrganizationAccess extends ApiResourceBase
{

}
