<?php

namespace Platformsh\Client\Session\Storage;

use Platformsh\Client\Session\SessionInterface;

interface SessionStorageInterface
{

    /**
     * @param SessionInterface $session
     *
     * @return bool
     */
    public function load(SessionInterface $session);

    /**
     * @param SessionInterface $session
     *
     * @return bool
     */
    public function save(SessionInterface $session);
}
