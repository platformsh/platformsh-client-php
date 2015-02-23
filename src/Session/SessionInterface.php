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
     * Add data to the session. New values will be merged with existing ones.
     *
     * @param array $data
     */
    public function add(array $data);

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
     * @return mixed
     */
    public function get($key);

    /**
     * Set the entire session data.
     *
     * @param array $data
     */
    public function setData(array $data);

    /**
     * Get all the session data.
     *
     * @return array
     */
    public function getData();

    /**
     * Set the session ID.
     *
     * @param string $id
     */
    public function setId($id);

    /**
     * Get the session ID.
     *
     * @return string
     */
    public function getId();

    /**
     * Load session data, if storage is defined.
     *
     * @param bool $reload
     *
     * @return bool
     */
    public function load($reload = false);

    /**
     * Save the session, if storage is defined.
     */
    public function save();

    /**
     * Clear the session data.
     */
    public function clear();
}
