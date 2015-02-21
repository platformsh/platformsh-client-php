<?php

namespace Platformsh\Client\Session\Storage;

use Platformsh\Client\Session\SessionInterface;

interface SessionStorageInterface
{

    /**
     * @throws \Exception
     *
     * @param SessionInterface $session
     */
    public function load(SessionInterface $session);

    /**
     * @throws \Exception
     *
     * @param SessionInterface $session
     */
    public function save(SessionInterface $session);
}
