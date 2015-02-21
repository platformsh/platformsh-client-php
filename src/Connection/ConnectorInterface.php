<?php

namespace Platformsh\Client\Connection;

use GuzzleHttp\ClientInterface;
use Platformsh\Client\Session\SessionInterface;

interface ConnectorInterface
{

    /**
     * @return SessionInterface
     */
    public function getSession();

    /**
     * Log in to Platform.sh.
     *
     * @param string $username
     * @param string $password
     * @param bool   $force
     *   Whether to re-authenticate even if the session appears to be logged
     *   in already.
     */
    public function authenticate($username, $password, $force = false);

    /**
     * @param string $endpoint
     *
     * @return ClientInterface
     */
    public function getClient($endpoint = null);

    /**
     * @param bool $debug
     */
    public function setDebug($debug);

    /**
     * @param bool $verifySsl
     */
    public function setVerifySsl($verifySsl);
}
