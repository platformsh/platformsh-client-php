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
     * Set the API token to use for Platform.sh requests.
     *
     * @param string $token
     *   The token value.
     * @param string $type
     *   The token type: 'exchange' for an API token (recommended), or 'access'
     *   for an OAuth 2.0 access token.
     */
    public function setApiToken($token, $type);

    /**
     * Get the configured accounts endpoint URL.
     *
     * @return string
     */
    public function getAccountsEndpoint();
}
