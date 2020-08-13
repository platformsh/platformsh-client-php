<?php

namespace Platformsh\Client\Model;

/**
 * Account information for a Platform.sh user.
 *
 * @property-read string $id
 * @property-read string $email
 * @property-read string $username
 * @property-read string $first_name
 * @property-read string $last_name
 * @property-read string $picture
 * @property-read string $country
 * @property-read string $company
 * @property-read string $website
 * @property-read bool   $deactivated
 * @property-read bool   $email_verified
 * @property-read bool   $mfa_enabled
 * @property-read string $created_at
 * @property-read string $updated_at
 */
class User extends ApiResourceBase {}
