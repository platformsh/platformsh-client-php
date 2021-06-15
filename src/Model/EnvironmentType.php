<?php

namespace Platformsh\Client\Model;

/**
 * @property-read string $id
 * @property-read array  $attributes
 */
class EnvironmentType extends Resource
{
    /**
     * Add a user to this environment type.
     *
     * @param string $id
     * @param string $role
     *
     * @return Result
     */
    public function addUser($id, $role)
    {
        return EnvironmentTypeAccess::create(['user' => $id, 'role' => $role], $this->getLink('#access'), $this->client);
    }

    /**
     * Get a user's access to this environment type.
     *
     * @param string $uuid
     *
     * @return EnvironmentTypeAccess|false
     */
    public function getUser($uuid)
    {
        return EnvironmentTypeAccess::get($uuid, $this->getLink('#access'), $this->client);
    }

    /**
     * Get the users with access to this environment type.
     *
     * @return EnvironmentTypeAccess[]
     */
    public function getUsers()
    {
        return EnvironmentTypeAccess::getCollection($this->getLink('#access'), 0, [], $this->client);
    }
}
