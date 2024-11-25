<?php

declare(strict_types=1);

namespace Platformsh\Client\Session\Storage;

interface SessionStorageInterface
{
    /**
     * Load data from a session.
     *
     * @param string $sessionId
     *
     * @return array
     */
    public function load($sessionId);

    /**
     * Save data to a session.
     *
     * @param string $sessionId
     */
    public function save($sessionId, array $data);
}
