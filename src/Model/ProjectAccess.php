<?php

namespace Platformsh\Client\Model;

/**
 * A user with access to a Platform.sh project.
 *
 * @property-read string $id
 * @property-read string $role
 */
class ProjectAccess extends Resource
{

    /** @var array */
    protected static $required = ['email'];

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
     * @deprecated use getEnvironmentAccess() instead
     *
     * @return string|false
     *   The user's environment role, or false if not found.
     */
    public function getEnvironmentRole(Environment $environment)
    {
        $access = $this->getEnvironmentAccess($environment);

        return $access ? $access->role : false;
    }

    /**
     * Get the user's role on an environment.
     *
     * @param Environment $environment
     *
     * @return EnvironmentAccess|false
     *   The user's environment access, or false if not found.
     */
    public function getEnvironmentAccess(Environment $environment)
    {
        $collection = EnvironmentAccess::getCollection($environment->getUri() . '/access', 0, [], $this->client);
        foreach ($collection as $access) {
            if ($access->user === $this->id) {
                return $access;
            }
        }

        return false;
    }

    /**
     * Change the user's environment-level role.
     *
     * @param Environment $environment
     * @param string      $newRole The new role ('admin', 'contributor',
     *                             or 'viewer').
     *
     * @return Activity|true
     *   An activity if one was returned (suggesting the environment is being
     *   redeployed), TRUE otherwise.
     */
    public function changeEnvironmentRole(Environment $environment, $newRole)
    {
        $access = $this->getEnvironmentAccess($environment);
        if ($access) {
            $data = $access->update(['role' => $newRole]);
        }
        else {
            $access = EnvironmentAccess::create(
              ['user' => $this->id, 'role' => $newRole],
              $environment->getUri() . '/access',
              $this->client
            );
            $data = $access->getData();
        }

        if (!isset($data['_embedded']['activities'][0])) {
            return true;
        }

        return Activity::wrap($data['_embedded']['activities'][0], $this->baseUrl, $this->client);
    }

    /**
     * Get the user's SSH keys.
     *
     * @param int $limit
     *
     * @return SshKey_Platform[]
     */
    public function getSshKeys($limit = 0)
    {
        return SshKey_Platform::getCollection($this->getUri() . '/keys', $limit, [], $this->client);
    }

    /**
     * Add an SSH key.
     *
     * @param string $key
     *
     * @return SshKey_Platform
     */
    public function addSshKey($key)
    {
        return SshKey_Platform::create(['key' => $key], $this->getUri() . '/keys', $this->client);
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
