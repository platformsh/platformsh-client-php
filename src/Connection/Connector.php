<?php

declare(strict_types=1);

namespace Platformsh\Client\Connection;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\RequestOptions;
use League\OAuth2\Client\Grant\ClientCredentials;
use League\OAuth2\Client\Grant\Password;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Platformsh\Client\Session\Session;
use Platformsh\Client\Session\SessionInterface;
use Platformsh\Client\Session\Storage\File;
use Platformsh\OAuth2\Client\Grant\ApiToken;
use Platformsh\OAuth2\Client\GuzzleMiddleware;
use Platformsh\OAuth2\Client\Provider\Platformsh;

class Connector implements ConnectorInterface
{
    protected array $config = [];

    protected ?ClientInterface $client = null;

    protected ?GuzzleMiddleware $oauthMiddleware = null;

    protected ?Platformsh $provider = null;

    protected SessionInterface $session;

    /**
     * @var array
     *
     * These keys are used for token storage for backwards compatibility with
     * the commerceguys/guzzle-oauth2-plugin package. The left-hand side is
     * the key in the AccessToken constructor. The right-hand side is the key
     * that will be stored.
     */
    private array $storageKeys = [
        'access_token' => 'accessToken',
        'refresh_token' => 'refreshToken',
        'token_type' => 'tokenType',
        'scope' => 'scope',
        'expires' => 'expires',
        'expires_in' => 'expiresIn',
    ];

    /**
     * @param array            $config
     *     Possible configuration keys are:
     *     - api_url (string): The API base URL.
     *     - auth_url (string): The Auth API URL.
     *     - centralized_permissions_enabled (bool): Whether the Centralized User Management API is enabled.
     *     - strict_project_references (bool): Whether to throw an exception if project references cannot be resolved.
     *     - token_url (string): The OAuth 2.0 token URL. Can be empty if auth_url is set.
     *     - revoke_url (string): The OAuth 2.0 revocation URL. Can be empty if auth_url is set.
     *     - certifier_url (string): The SSH certificate issuer URL. Can be empty if auth_url is set.
     *     - client_id (string): The OAuth2 client ID for this client.
     *     - api_token (string): An API token to use for authentication.
     *     - client_secret (string): The OAuth2 client secret for this client.
     *       If set, and api_token is empty, then client credentials-based authentication will be used.
     *     - scopes (string[]): Scope(s) to request when using client credentials-based authentication.
     *     - debug (bool): Whether or not Guzzle debugging should be enabled
     *       (default: false).
     *     - verify (bool): Whether or not SSL verification should be enabled
     *       (default: true).
     *     - user_agent (string): The HTTP User-Agent for API requests.
     *     - headers (array<string, string>): Additional headers to send to the API (an associative array of header names and values).
     *     - middlewares (callable[]): Additional Guzzle HTTP handlers.
     *     - cache (array|bool): Caching. Set to true to enable in-memory
     *       caching, to false (the default) to disable caching, or to an array
     *       of options as expected by the Guzzle cache subscriber.
     *     - proxy (array|string): A proxy setting, passed to Guzzle directly.
     *       Use a string to specify an HTTP proxy, or an array to specify
     *       different proxies for different protocols.
     *     - timeout (float): The default request timeout for any request, in
     *       seconds (default: 60).
     *     - connect_timeout (float): The default connection timeout for any
     *       request, in seconds (default: 60).
     *     - on_refresh_error: A callback to run when a refresh token error is
     *       received. It will be passed a Guzzle BadResponseException, and
     *       should return an AccessToken or null.
     *     - on_step_up_auth_response: A callback to run when a refresh token error is
     *       received. It will be passed a Guzzle ResponseInterface, and
     *       should return an AccessToken or null.
     */
    public function __construct(array $config = [], SessionInterface $session = null)
    {
        if (isset($config['accounts'])) {
            \trigger_error('The "accounts" URL option is deprecated. APIs are accessed based on the "api_url" and OAuth 2.0 URL options instead.', E_USER_DEPRECATED);
        }

        $defaults = [
            'api_url' => 'https://api.platform.sh',
            'accounts' => 'https://api.platform.sh/',
            'client_id' => 'platformsh-client-php',
            'client_secret' => '',
            'scopes' => [],
            'debug' => false,
            'verify' => true,
            'user_agent' => null,
            'headers' => [],
            'subscribers' => [],
            'cache' => false,
            'auth_url' => 'https://auth.api.platform.sh',
            'revoke_url' => '',
            'token_url' => '',
            'certifier_url' => '',
            'centralized_permissions_enabled' => false,
            'strict_project_references' => false,
            'proxy' => null,
            'timeout' => 60.0,
            'connect_timeout' => 60.0,
            'api_token' => null,
            'api_token_type' => 'exchange',
            'gzip' => extension_loaded('zlib'),
            'on_refresh_start' => null,
            'on_refresh_end' => null,
            'on_refresh_error' => null,
            'on_step_up_auth_response' => null,
        ];
        $this->config = $config + $defaults;

        if (! isset($this->config['user_agent'])) {
            $this->config['user_agent'] = $this->defaultUserAgent();
        }

        if (! empty($this->config['auth_url'])) {
            if (empty($this->config['token_url'])) {
                $this->config['token_url'] = rtrim($this->config['auth_url'], '/') . '/oauth2/token';
            }
            if (empty($this->config['revoke_url'])) {
                $this->config['revoke_url'] = rtrim($this->config['auth_url'], '/') . '/oauth2/revoke';
            }
            if (empty($this->config['certifier_url'])) {
                $this->config['certifier_url'] = $this->config['auth_url'];
            }
        }

        if (isset($session)) {
            $this->session = $session;
        } else {
            if ($this->config['api_token'] && $this->config['api_token_type'] === 'access') {
                // If an access token is set directly, default to a session
                // with no storage.
                $this->session = new Session();
            } else {
                // Otherwise, assign file storage to the session by default.
                // This reduces unnecessary access token refreshes.
                $this->session = new Session();
                $this->session->setStorage(new File());
            }
        }
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get the configured accounts endpoint URL.
     *
     * @deprecated Use Connector::getApiUrl() instead
     */
    public function getAccountsEndpoint(): string
    {
        return $this->config['accounts'];
    }

    /**
     * Get the configured API gateway URL (without trailing slash).
     */
    public function getApiUrl(): string
    {
        return rtrim($this->config['api_url'], '/');
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException if tokens cannot be revoked.
     */
    public function logOut(): void
    {
        $this->client = null;
        try {
            $this->revokeTokens();
        } catch (RequestException $e) {
            // Retry the request once, if we received a retry status.
            $retryStatuses = [408, 429, 502, 503, 504];
            if ($e->getResponse() && in_array($e->getResponse()->getStatusCode(), $retryStatuses, true)) {
                $this->revokeTokens();
            } else {
                trigger_error($e->getMessage());
            }
        } finally {
            $this->session->clear();
            $this->session->save();
        }
    }

    public function getSession(): Session|SessionInterface
    {
        return $this->session;
    }

    /**
     * Returns the access token saved in the session, if any.
     */
    public function getAccessToken(): false|string|null
    {
        return $this->session->get('accessToken');
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \League\OAuth2\Client\Provider\Exception\IdentityProviderException
     */
    public function logIn(string $username, string $password, bool $force = false, int|string $totp = null): void
    {
        if (! $force && $this->isLoggedIn() && $this->session->get('username') === $username) {
            return;
        }
        if ($this->isLoggedIn()) {
            $this->logOut();
        }
        $token = $this->getOAuth2Provider()->getAccessToken(new Password(), [
            'username' => $username,
            'password' => $password,
            'totp' => $totp,
        ]);
        $this->session->set('username', $username);
        $this->saveToken($token);
    }

    /**
     * Save an access token to the session.
     */
    public function saveToken(AccessTokenInterface $token): void
    {
        if ($this->config['api_token'] && $this->config['api_token_type'] === 'access') {
            return;
        }
        foreach ($token->jsonSerialize() as $name => $value) {
            if (isset($this->storageKeys[$name])) {
                $this->session->set($this->storageKeys[$name], $value);
            }
        }
        $this->session->save();
    }

    public function isLoggedIn(): bool
    {
        return $this->session->get($this->storageKeys['access_token']) || $this->config['api_token'] || $this->config['client_secret'];
    }

    public function setApiToken(string $token, string $type): void
    {
        $this->config['api_token'] = $token;
        if (! in_array($type, ['access', 'exchange'], true)) {
            throw new \InvalidArgumentException('Invalid API token type: ' . $type);
        }
        $this->config['api_token_type'] = $type;
        if (isset($this->oauthMiddleware)) {
            $this->oauthMiddleware = null;
        }
    }

    public function getClient(): ClientInterface
    {
        if (! isset($this->client)) {
            $stack = HandlerStack::create();
            $stack->push($this->getOauthMiddleware());

            $config = [
                'handler' => $stack,
                RequestOptions::HEADERS => [
                    'User-Agent' => $this->config['user_agent'],
                ],
                RequestOptions::DEBUG => $this->config['debug'],
                RequestOptions::VERIFY => $this->config['verify'],
                RequestOptions::PROXY => $this->config['proxy'],
                RequestOptions::TIMEOUT => $this->config['timeout'],
                RequestOptions::CONNECT_TIMEOUT => $this->config['connect_timeout'],
                'auth' => 'oauth2',
            ];

            if (! empty($this->config['middlewares'])) {
                foreach ($this->config['middlewares'] as $middleware) {
                    $stack->push($middleware);
                }
            }

            if (! empty($this->config['headers'])) {
                $config[RequestOptions::HEADERS] += $this->config['headers'];
            }

            if ($this->config['gzip']) {
                $config[RequestOptions::DECODE_CONTENT] = true;
                $config[RequestOptions::HEADERS]['Accept-Encoding'] = 'gzip';
            }

            if ($url = $this->getApiUrl()) {
                $config['base_uri'] = $url;
            }

            $this->client = new Client($config);
        }

        return $this->client;
    }

    public function getOAuth2Provider(): Platformsh
    {
        return $this->provider ?: new Platformsh([
            'clientId' => $this->config['client_id'],
            'clientSecret' => $this->config['client_secret'],
            'token_url' => $this->config['token_url'],
            'api_url' => $this->config['api_url'],
            'debug' => $this->config['debug'],
            'verify' => $this->config['verify'],
            'proxy' => $this->config['proxy'],
        ]);
    }

    /**
     * Load the current access token.
     */
    protected function loadToken(): ?AccessToken
    {
        if ($this->config['api_token'] && $this->config['api_token_type'] === 'access') {
            return new AccessToken([
                'access_token' => $this->config['api_token'],
                // Skip local expiry checking.
                'expires' => 2147483647,
            ]);
        }
        if (! $this->session->get($this->storageKeys['access_token'])) {
            return null;
        }

        // These keys are used for saving in the session for backwards
        // compatibility with the commerceguys/guzzle-oauth2-plugin package.
        $values = [];
        foreach ($this->storageKeys as $tokenKey => $sessionKey) {
            $value = $this->session->get($sessionKey);
            if ($value !== null) {
                $values[$tokenKey] = $value;
            }
        }

        return new AccessToken($values);
    }

    /**
     * Get an OAuth2 middleware to add to Guzzle clients.
     *
     * @throws \RuntimeException
     */
    protected function getOauthMiddleware(): GuzzleMiddleware
    {
        if (! $this->oauthMiddleware) {
            if (! $this->isLoggedIn()) {
                throw new \RuntimeException('Not logged in');
            }

            $grant = new ClientCredentials();
            $grantOptions = [];

            // Set up the "exchange" (normal) API token type.
            if ($this->config['api_token'] && $this->config['api_token_type'] !== 'access') {
                $grant = new ApiToken();
                $grantOptions['api_token'] = $this->config['api_token'];
            }

            if ($this->config['client_secret']) {
                $grantOptions['client_secret'] = $this->config['client_secret'];
            }

            if ($this->config['scopes']) {
                $grantOptions['scope'] = implode(' ', (array) $this->config['scopes']);
            }

            $this->oauthMiddleware = new GuzzleMiddleware($this->getOAuth2Provider(), $grant, $grantOptions);
            $this->oauthMiddleware->setTokenSaveCallback(function (AccessToken $token) {
                $this->saveToken($token);
            });

            // If an access token is already available (via an API token or via
            // the session) then set it in the middleware in advance.
            if ($accessToken = $this->loadToken()) {
                $this->oauthMiddleware->setAccessToken($accessToken);
            }

            if ($this->config['on_refresh_start'] !== null) {
                $this->oauthMiddleware->setOnRefreshStart($this->config['on_refresh_start']);
            }
            if ($this->config['on_refresh_end'] !== null) {
                $this->oauthMiddleware->setOnRefreshEnd($this->config['on_refresh_end']);
            }
            if ($this->config['on_refresh_error'] !== null) {
                $this->oauthMiddleware->setOnRefreshError($this->config['on_refresh_error']);
            }
            if ($this->config['on_step_up_auth_response'] !== null) {
                $this->oauthMiddleware->setOnStepUpAuthResponse($this->config['on_step_up_auth_response']);
            }
        }

        return $this->oauthMiddleware;
    }

    private function defaultUserAgent(): string
    {
        $version = trim(file_get_contents(__DIR__ . '/../../version.txt')) ?: '2.0.x';

        return sprintf(
            '%s/%s (%s; %s; PHP %s)',
            'Platform.sh-Client-PHP',
            $version,
            php_uname('s'),
            php_uname('r'),
            PHP_VERSION
        );
    }

    /**
     * Get a configured OAuth 2.0 URL.
     *
     * @param string $key Either 'token_url' or 'revoke_url'
     */
    private function getOAuthUrl(string $key): string
    {
        $url = $this->config[$key];

        // Backwards compatibility.
        if (! str_contains($url, '//')) {
            $url = Utils::uriFor($this->config['accounts'])
                ->withPath($this->config[$key])
                ->__toString();
        }

        return $url;
    }

    /**
     * Revokes the access and refresh tokens saved in the session.
     *
     * @see Connector::logOut()
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function revokeTokens(): void
    {
        $revocations = array_filter([
            'refresh_token' => $this->session->get('refreshToken'),
            'access_token' => $this->session->get('accessToken'),
        ]);
        $url = $this->getOAuthUrl('revoke_url');
        foreach ($revocations as $type => $token) {
            $options = [
                'form_params' => [
                    'client_id' => $this->config['client_id'],
                    'client_secret' => $this->config['client_secret'],
                    'token' => $token,
                    'token_type_hint' => $type,
                ],
                'auth' => false,
            ];
            $this->getClient()->request('post', $url, $options);
        }
    }
}
