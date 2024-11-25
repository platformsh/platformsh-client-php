<?php

declare(strict_types=1);

namespace Platformsh\Client\Model;

/**
 * A user with access to a Platform.sh project.
 *
 * @deprecated This can and should be replaced by ProjectUserAccess when the "Centralized Permissions" API is available
 *
 * @see \Platformsh\Client\Model\UserAccess\ProjectUserAccess
 *
 * @property-read string $id
 * @property-read string $role
 */
class ProjectAccess extends ApiResourceBase
{
    public const ROLE_ADMIN = 'admin';

    public const ROLE_VIEWER = 'viewer';

    public static array $roles = [self::ROLE_ADMIN, self::ROLE_VIEWER];

    protected static array $required = ['role'];

    /**
     * Get the account information for this user.
     *
     * @throws \Exception
     */
    public function getAccount(): Account
    {
        $uuid = $this->getProperty('id');
        $url = $this->makeAbsoluteUrl('/api/users');
        $account = Account::get($uuid, $url, $this->client);
        if (! $account) {
            throw new \Exception('Account not found for user: ' . $uuid);
        }
        return $account;
    }

    /**
     * Get the user's role on an environment.
     *
     * @deprecated use Environment::getUser() instead
     *
     * @return string|false
     *   The user's environment role, or false if not found.
     */
    public function getEnvironmentRole(Environment $environment): false|string
    {
        $access = $environment->getUser($this->id);

        return $access ? $access->role : false;
    }

    /**
     * Change the user's environment-level role.
     *
     * @param string $newRole The new role (see EnvironmentAccess::$roles).
     */
    public function changeEnvironmentRole(Environment $environment, string $newRole): Result
    {
        $access = $environment->getUser($this->id);
        if ($access) {
            if ($access->role === $newRole) {
                throw new \InvalidArgumentException('There is nothing to change');
            }

            return $access->update([
                'role' => $newRole,
            ]);
        }

        return $environment->addUser($this->id, $newRole);
    }

    /**
     * Check whether the user is editable.
     */
    public function isEditable(): bool
    {
        return $this->operationAvailable('edit');
    }

    protected static function checkProperty(string $property, mixed $value): array
    {
        $errors = [];
        if ($property === 'email' && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email address: '{$value}'";
        } elseif ($property === 'role' && ! in_array($value, static::$roles, true)) {
            $errors[] = "Invalid role: '{$value}'";
        }
        return $errors;
    }
}
