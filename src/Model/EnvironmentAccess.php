<?php

declare(strict_types=1);

namespace Platformsh\Client\Model;

/**
 * A record establishing a user's access to a Platform.sh environment.
 *
 * @deprecated This can and should be replaced by ProjectUserAccess when the "Centralized Permissions" API is available
 *
 * @see \Platformsh\Client\Model\UserAccess\ProjectUserAccess
 *
 * @property-read string $user The user UUID
 * @property-read string $role The user's role
 * @property-read string $project The project ID
 * @property-read string $environment The environment ID
 */
class EnvironmentAccess extends ApiResourceBase
{
    public const ROLE_ADMIN = 'admin';

    public const ROLE_VIEWER = 'viewer';

    public const ROLE_CONTRIBUTOR = 'contributor';

    public static array $roles = [self::ROLE_ADMIN, self::ROLE_VIEWER, self::ROLE_CONTRIBUTOR];

    protected static array $required = ['role'];

    public function getLink(string $rel, bool $absolute = true): string
    {
        // @todo double-check whether the resource does contain the #edit link
        if ($rel === '#edit' && ! $this->hasLink($rel)) {
            return $this->getUri($absolute);
        }

        return parent::getLink($rel, $absolute);
    }

    protected static function checkProperty(string $property, mixed $value): array
    {
        $errors = [];
        if ($property === 'role' && ! in_array($value, static::$roles, true)) {
            $errors[] = "Invalid environment role: '{$value}'";
        }

        return $errors;
    }
}
