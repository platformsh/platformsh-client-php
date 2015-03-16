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
        $account = Account::get($uuid, '/api/users', $this->client);
        if (!$account) {
            throw new \Exception("Account not found for user: " . $uuid);
        }
        return $account;
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
    public static function check(array $data)
    {
        $errors = parent::check($data);
        if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email address: '{$data['email']}'";
        }
        if (isset($data['role']) && !in_array($data['role'], [self::ROLE_ADMIN, self::ROLE_VIEWER])) {
            $errors[] = "Invalid role: '{$data['role']}";
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
