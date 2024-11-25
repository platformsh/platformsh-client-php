<?php

declare(strict_types=1);

namespace Platformsh\Client\Connection;

use GuzzleHttp\ClientInterface;
use Platformsh\Client\Session\SessionInterface;

interface ConnectorInterface
{
    /**
     * Get the session instance for this connection.
     */
    public function getSession(): SessionInterface;

    /**
     * Log in to Platform.sh.
     *
     * @param bool $force
     *   Whether to re-authenticate even if the session appears to be logged
     *   in already.
     * @param int|string|null $totp
     *   Time-based one-time password (two-factor authentication).
     */
    public function logIn(string $username, string $password, bool $force = false, int|string $totp = null);

    /**
     * Log out.
     */
    public function logOut();

    /**
     * Check whether the user is logged in.
     */
    public function isLoggedIn(): bool;

    /**
     * Get an authenticated Guzzle client.
     *
     * This will fail if the user is not logged in.
     */
    public function getClient(): ClientInterface;

    /**
     * Set the API token to use for Platform.sh requests.
     *
     * @param string $token
     *   The token value.
     * @param string $type
     *   The token type: 'exchange' for an API token (recommended), or 'access'
     *   for an OAuth 2.0 access token.
     */
    public function setApiToken(string $token, string $type);

    /**
     * Get the configured API gateway URL (without trailing slash).
     */
    public function getApiUrl(): string;
}
