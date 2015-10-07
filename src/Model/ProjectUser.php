<?php

namespace Platformsh\Client\Model;

/**
 * A user with access to a Platform.sh project.
 *
 * @property-read string $id
 * @property-read string $role
 */
class ProjectUser extends Resource
{

    /** @var array */
    protected static $required = ['email'];

    const ROLE_ADMIN = 'admin';
    const ROLE_VIEWER = 'viewer';

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
     * @throws \Exception
     *
     * @return string
     *   The user's environment-level role ('admin', 'contributor', or
     *   'viewer').
     */
    public function getEnvironmentRole(Environment $environment)
    {
        $results = $this->sendRequest($environment->getUri() . '/access');
        foreach ($results as $result) {
            if ($result['id'] === $this->id) {
                return $result['role'];
            }
        }

        return $this->getProperty('role');
    }

    /**
     * Change the user's environment-level role.
     *
     * @param Environment $environment
     * @param string      $newRole The new role ('admin', 'contributor',
     *                             or 'viewer').
     *
     * @return Activity
     */
    public function changeEnvironmentRole(Environment $environment, $newRole)
    {
        if (!in_array($newRole, ['admin', 'contributor', 'viewer'])) {
            throw new \InvalidArgumentException("Invalid role: $newRole");
        }

        $data = $this->sendRequest($environment->getUri() . '/access', 'post', [
          'json' => ['user' => $this->id, 'role' => $newRole],
        ]);

        if (!isset($data['_embedded']['activities'][0])) {
            throw new \RuntimeException('Expected activity not found');
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
        elseif ($property === 'role' && !in_array($value, [self::ROLE_ADMIN, self::ROLE_VIEWER])) {
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
