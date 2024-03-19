<?php

namespace Platformsh\Client\Model\Ref;

use Platformsh\Client\DataStructure\ReadOnlyStructureTrait;

/**
 * @property-read string $id
 *   The user ID (typically a UUIDv4).
 * @property-read string $username
 *   The username.
 * @property-read string $email
 *   The user's verified email address.
 * @property-read string $first_name
 *   The user's first name.
 * @property-read string $last_name
 *   The user's last name.
 * @property-read string $picture
 *   A public URL to the user's profile picture.
 * @property-read bool $mfa_enabled
 *   Whether the user has MFA enabled. Note they may have MFA verified via an SSO provider (see sso_enabled).
 * @property-read bool $sso_enabled
 *   Whether the user has SSO enabled.
 * @property-read string $created_at
 *   When the user was created (an RFC 3339 timestamp).
 * @property-read string $updated_at
 *   When these user properties were last updated (an RFC 3339 timestamp).
 */
class UserRef
{
    use ReadOnlyStructureTrait;
}
