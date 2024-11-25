<?php

declare(strict_types=1);

namespace Platformsh\Client\Session;

use Platformsh\Client\Session\Storage\SessionStorageInterface;

interface SessionInterface
{
    /**
     * Set the storage for this session.
     */
    public function setStorage(SessionStorageInterface $storage);

    /**
     * Set a particular session value.
     */
    public function set(string $key, mixed $value);

    /**
     * Get a session value.
     *
     * @return mixed|null
     */
    public function get(string $key): mixed;

    /**
     * Save the session, if storage is defined.
     */
    public function save();

    /**
     * Clear the session data.
     */
    public function clear();
}
