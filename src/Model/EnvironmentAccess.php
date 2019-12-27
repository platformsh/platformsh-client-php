<?php

namespace Platformsh\Client\Model;

/**
 * A record establishing a user's access to a Platform.sh environment.
 *
 * @property-read string $user The user UUID
 * @property-read string $role The user's role
 * @property-read string $project The project ID
 * @property-read string $environment The environment ID
 */
class EnvironmentAccess extends ApiResourceBase
{

    /** @var array */
    protected static $required = ['role'];

    const ROLE_ADMIN = 'admin';
    const ROLE_VIEWER = 'viewer';
    const ROLE_CONTRIBUTOR = 'contributor';

    public static $roles = [self::ROLE_ADMIN, self::ROLE_VIEWER, self::ROLE_CONTRIBUTOR];

    /**
     * @inheritdoc
     */
    protected static function checkProperty($property, $value)
    {
        $errors = [];
        if ($property === 'role' && !in_array($value, static::$roles)) {
            $errors[] = "Invalid environment role: '$value'";
        }

        return $errors;
    }

    /**
     * {@inheritdoc}
     */
    public function getLink($rel, $absolute = true)
    {
        // @todo double-check whether the resource does contain the #edit link
        if ($rel === "#edit" && !$this->hasLink($rel)) {
            return $this->getUri($absolute);
        }

        return parent::getLink($rel, $absolute);
    }
}
