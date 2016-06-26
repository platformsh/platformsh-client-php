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
     * @param string|int $totp
     *   Time-based one-time password (two-factor authentication).
     */
    public function logIn($username, $password, $force = false, $totp = null);

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
     * @return ClientInterface
     */
    public function getClient();

    /**
     * Get the configured accounts endpoint URL.
     *
     * @return string
     */
    public function getAccountsEndpoint();

    /**
     * Set the API token to use for Platform.sh requests.
     *
     * @param string $token
     *   The token value.
     * @param string|null $type
     *   The token type ('access' for personal access tokens, or 'exchange'
     *   for exchangeable tokens).
     */
    public function setApiToken($token, $type = 'access');
}
