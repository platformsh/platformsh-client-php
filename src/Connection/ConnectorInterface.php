<?php

namespace Platformsh\Client\Connection;

use GuzzleHttp\ClientInterface;
use Platformsh\Client\Session\SessionInterface;

interface ConnectorInterface
{

    /**
     * Get the session instance for this connection.
     *
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
    public function logIn($username, $password, $force = false);

    /**
     * Log out.
     */
    public function logOut();

    /**
     * Get an authenticated Guzzle client.
     *
     * @param string $endpoint
     *
     * @return ClientInterface
     */
    public function getClient($endpoint = null);

    /**
     * Enable or disable Guzzle debugging.
     *
     * @param bool $debug
     */
    public function setDebug($debug);

    /**
     * Enable or disable verifying SSL.
     *
     * @param bool $verifySsl
     */
    public function setVerifySsl($verifySsl);
}
