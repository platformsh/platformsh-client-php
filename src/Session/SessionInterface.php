<?php

namespace Platformsh\Client\Session;

use Platformsh\Client\Session\Storage\SessionStorageInterface;

interface SessionInterface
{

    /**
     * Set the storage for this session.
     *
     * @param SessionStorageInterface $storage
     */
    public function setStorage(SessionStorageInterface $storage);

    /**
     * Set a particular session value.
     *
     * @param string $key
     * @param mixed  $value
     */
    public function set($key, $value);

    /**
     * Get a session value.
     *
     * @param string $key
     *
     * @return mixed|null
     */
    public function get($key);

    /**
     * Save the session, if storage is defined.
     */
    public function save();

    /**
     * Clear the session data.
     */
    public function clear();
}
