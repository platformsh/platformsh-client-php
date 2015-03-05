<?php

namespace Platformsh\Client\Model;

class ProjectUser extends Resource
{

    /** @var array */
    protected static $required = ['email'];

    const ROLE_ADMIN = 'admin';
    const ROLE_VIEWER = 'viewer';

    /**
     * Get the account information for this user.
     *
     * @return Account
     */
    public function getAccount()
    {
        return Account::get($this->getProperty('id'), '/api/users', $this->client);
    }

    /**
     * Get the user's SSH keys.
     *
     * @param int $limit
     *
     * @return SshKey[]
     */
    public function getSshKeys($limit = 0)
    {
        return SshKey::getCollection($this->getUri() . '/keys', $limit, [], $this->client);
    }

    /**
     * Add an SSH key.
     *
     * @param string $key
     *
     * @return SshKey
     */
    public function addSshKey($key)
    {
        return SshKey::create(['key' => $key], $this->getUri() . '/keys', $this->client);
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
