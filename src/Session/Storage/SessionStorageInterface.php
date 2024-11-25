<?php

declare(strict_types=1);

namespace Platformsh\Client\Session\Storage;

interface SessionStorageInterface
{
    /**
     * Load data from a session.
     */
    public function load(string $sessionId): array;

    /**
     * Save data to a session.
     */
    public function save(string $sessionId, array $data);
}
