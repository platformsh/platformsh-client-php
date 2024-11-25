<?php

declare(strict_types=1);

namespace Platformsh\Client\Model;

/**
 * @property-read string $id
 * @property-read array  $attributes
 */
class EnvironmentType extends ApiResourceBase
{
    /**
     * Add a user to this environment type.
     */
    public function addUser(string $id, string $role): Result
    {
        return EnvironmentTypeAccess::create([
            'user' => $id,
            'role' => $role,
        ], $this->getLink('#access'), $this->client);
    }

    /**
     * Get a user's access to this environment type.
     */
    public function getUser(string $uuid): false|EnvironmentTypeAccess
    {
        return EnvironmentTypeAccess::get($uuid, $this->getLink('#access'), $this->client);
    }

    /**
     * Get the users with access to this environment type.
     *
     * @return EnvironmentTypeAccess[]
     */
    public function getUsers(): array
    {
        return EnvironmentTypeAccess::getCollection($this->getLink('#access'), 0, [], $this->client);
    }
}
