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
     *
     * @throws \InvalidArgumentException If the credentials are invalid.
     */
    public function logIn($username, $password, $force = false);

    /**
     * Log out.
     */
    public function logOut();

    /**
     * Check whether the user is logged in.
     *
     * @return bool
     */
    public function isLoggedIn();

    /**
     * Get an authenticated Guzzle client.
     *
     * This will fail if the user is not logged in.
     *
     * @param string $endpoint
     *
     * @return ClientInterface
     */
    public function getClient($endpoint = null);

}
