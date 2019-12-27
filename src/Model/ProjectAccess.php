<?php

namespace Platformsh\Client\Model;

/**
 * A user with access to a Platform.sh project.
 *
 * @property-read string $id
 * @property-read string $role
 */
class ProjectAccess extends ApiResourceBase
{

    /** @var array */
    protected static $required = ['role'];

    const ROLE_ADMIN = 'admin';
    const ROLE_VIEWER = 'viewer';

    public static $roles = [self::ROLE_ADMIN, self::ROLE_VIEWER];

    /**
     * Get the account information for this user.
     *
     * @throws \Exception
     *
     * @return Account
     */
    public function getAccount()
    {
        $uuid = $this->getProperty('id');
        $url = $this->makeAbsoluteUrl('/api/users');
        $account = Account::get($uuid, $url, $this->client);
        if (!$account) {
            throw new \Exception("Account not found for user: " . $uuid);
        }
        return $account;
    }

    /**
     * Get the user's role on an environment.
     *
     * @param Environment $environment
     *
     * @deprecated use Environment::getUser() instead
     *
     * @return string|false
     *   The user's environment role, or false if not found.
     */
    public function getEnvironmentRole(Environment $environment)
    {
        $access = $environment->getUser($this->id);

        return $access ? $access->role : false;
    }

    /**
     * Change the user's environment-level role.
     *
     * @param Environment $environment
     * @param string $newRole The new role (see EnvironmentAccess::$roles).
     *
     * @return Result
     */
    public function changeEnvironmentRole(Environment $environment, $newRole)
    {
        $access = $environment->getUser($this->id);
        if ($access) {
            if ($access->role === $newRole) {
                throw new \InvalidArgumentException("There is nothing to change");
            }

            return $access->update(['role' => $newRole]);
        }

        return $environment->addUser($this->id, $newRole);
    }

    /**
     * @inheritdoc
     */
    protected static function checkProperty($property, $value)
    {
        $errors = [];
        if ($property === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email address: '$value'";
        }
        elseif ($property === 'role' && !in_array($value, static::$roles)) {
            $errors[] = "Invalid role: '$value'";
        }
        return $errors;
    }

    /**
     * Check whether the user is editable.
     *
     * @return bool
     */
    public function isEditable()
    {
        return $this->operationAvailable('edit');
    }
}
