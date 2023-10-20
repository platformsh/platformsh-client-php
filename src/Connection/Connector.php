<?php

namespace Platformsh\Client\Connection;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Utils;
use League\OAuth2\Client\Grant\ClientCredentials;
use League\OAuth2\Client\Grant\Password;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Platformsh\Client\Session\Session;
use Platformsh\Client\Session\SessionInterface;
use Platformsh\OAuth2\Client\Provider\Platformsh;
use Platformsh\OAuth2\Client\Grant\ApiToken;
use Platformsh\OAuth2\Client\GuzzleMiddleware;
use Platformsh\Client\Session\Storage\File;

class Connector implements ConnectorInterface
{
    /** @var array */
    protected $config = [];

    /** @var ClientInterface|null */
    protected $client;

    /** @var callable|null */
    protected $oauthMiddleware;

    /** @var AbstractProvider|null */
    protected $provider;

    /** @var SessionInterface */
    protected $session;

    /**
     * @var array $storageKeys
     *
     * These keys are used for token storage for backwards compatibility with
     * the commerceguys/guzzle-oauth2-plugin package. The left-hand side is
     * the key in the AccessToken constructor. The right-hand side is the key
     * that will be stored.
     */
    private $storageKeys = [
        'access_token' => 'accessToken',
        'refresh_token' => 'refreshToken',
        'token_type' => 'tokenType',
        'scope' => 'scope',
        'expires' => 'expires',
        'expires_in' => 'expiresIn',
        'resource_owner_id' => 'resourceOwnerId,'
    ];

    /**
     * @param array            $config
     *     Possible configuration keys are:
     *     - api_url (string): The API base URL.
     *     - auth_url (string): The Auth API URL.
     *     - auth_api_enabled (bool): Whether the Auth API is enabled. Always true if auth_url is set.
     *     - centralized_permissions_enabled (bool): Whether the Centralized User Management API is enabled.
     *     - strict_project_references (bool): Whether to throw an exception if project references cannot be resolved.
     *     - token_url (string): The OAuth 2.0 token URL. Can be empty if auth_url is set.
     *     - revoke_url (string): The OAuth 2.0 revocation URL. Can be empty if auth_url is set.
     *     - certifier_url (string): The SSH certificate issuer URL. Can be empty if auth_url is set.
     *     - client_id (string): The OAuth2 client ID for this client.
     *     - debug (bool): Whether or not Guzzle debugging should be enabled
     *       (default: false).
     *     - verify (bool): Whether or not SSL verification should be enabled
     *       (default: true).
     *     - user_agent (string): The HTTP User-Agent for API requests.
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
     * @param SessionInterface $session
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
          'debug' => false,
          'verify' => true,
          'user_agent' => null,
          'cache' => false,
          'auth_url' => 'https://auth.api.platform.sh',
          'auth_api_enabled' => true,
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
          'on_refresh_error' => null,
        ];
        $this->config = $config + $defaults;

        if (!isset($this->config['user_agent'])) {
            $this->config['user_agent'] = $this->defaultUserAgent();
        }

        if (!empty($this->config['auth_url'])) {
            $this->config['auth_api_enabled'] = true;
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

        if (!empty($this->config['centralized_permissions_enabled']) && empty($this->config['auth_api_enabled'])) {
            throw new \InvalidArgumentException('auth_api_enabled is needed if centralized_permissions_enabled is true');
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

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @return string
     */
    private function defaultUserAgent()
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
     * Get the configured accounts endpoint URL.
     *
     * @deprecated Use Connector::getApiUrl() instead
     *
     * @return string
     */
    public function getAccountsEndpoint()
    {
        return $this->config['accounts'];
    }

    /**
     * Get the configured API gateway URL (without trailing slash).
     *
     * @return string
     */
    public function getApiUrl()
    {
        return rtrim($this->config['api_url'], '/');
    }

    /**
     * @inheritdoc
     *
     * @throws \GuzzleHttp\Exception\GuzzleException if tokens cannot be revoked.
     */
    public function logOut()
    {
        $this->client = null;
        try {
            $this->revokeTokens();
        } catch (RequestException $e) {
            // Retry the request once, if we received a retry status.
            $retryStatuses = [408, 429, 502, 503, 504];
            if ($e->getResponse() && in_array($e->getResponse()->getStatusCode(), $retryStatuses)) {
                $this->revokeTokens();
            } else {
                trigger_error($e->getMessage());
            }
        } finally {
            $this->session->clear();
            $this->session->save();
        }
    }

    /**
     * Get a configured OAuth 2.0 URL.
     *
     * @param string $key Either 'token_url' or 'revoke_url'
     *
     * @return string
     */
    private function getOAuthUrl($key)
    {
        $url = $this->config[$key];

        // Backwards compatibility.
        if (strpos($url, '//') === false) {
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
    private function revokeTokens()
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

    /**
     * @inheritdoc
     */
    public function getSession()
    {
        return $this->session;
    }

    /**
     * Returns the access token saved in the session, if any.
     *
     * @return false|string
     */
    public function getAccessToken()
    {
        return $this->session->get('accessToken');
    }

    /**
     * {@inheritdoc}
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \League\OAuth2\Client\Provider\Exception\IdentityProviderException
     */
    public function logIn($username, $password, $force = false, $totp = null)
    {
        if (!$force && $this->isLoggedIn() && $this->session->get('username') === $username) {
            return;
        }
        if ($this->isLoggedIn()) {
            $this->logOut();
        }
        $token = $this->getProvider()->getAccessToken(new Password(), [
            'username' => $username,
            'password' => $password,
            'totp' => $totp,
        ]);
        $this->session->set('username', $username);
        $this->saveToken($token);
    }

    private function getProvider()
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
     * Save an access token to the session.
     *
     * @param AccessTokenInterface $token
     */
    public function saveToken(AccessTokenInterface $token)
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

    /**
     * Load the current access token.
     *
     * @return AccessToken|null
     */
    protected function loadToken()
    {
        if ($this->config['api_token'] && $this->config['api_token_type'] === 'access') {
            return new AccessToken([
                'access_token' => $this->config['api_token'],
                // Skip local expiry checking.
                'expires' => 2147483647,
            ]);
        }
        if (!$this->session->get($this->storageKeys['access_token'])) {
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
     * @inheritdoc
     */
    public function isLoggedIn()
    {
        return $this->session->get($this->storageKeys['access_token']) || $this->config['api_token'];
    }

    /**
     * Get an OAuth2 middleware to add to Guzzle clients.
     *
     * @throws \RuntimeException
     *
     * @return GuzzleMiddleware
     */
    protected function getOauthMiddleware()
    {
        if (!$this->oauthMiddleware) {
            if (!$this->isLoggedIn()) {
                throw new \RuntimeException('Not logged in');
            }

            $grant = new ClientCredentials();
            $grantOptions = [];

            // Set up the "exchange" (normal) API token type.
            if ($this->config['api_token'] && $this->config['api_token_type'] !== 'access') {
                $grant = new ApiToken();
                $grantOptions['api_token'] = $this->config['api_token'];
            }

            $this->oauthMiddleware = new GuzzleMiddleware($this->getProvider(), $grant, $grantOptions);
            $this->oauthMiddleware->setTokenSaveCallback(function (AccessToken $token) {
                $this->saveToken($token);
            });

            // If an access token is already available (via an API token or via
            // the session) then set it in the middleware in advance.
            if ($accessToken = $this->loadToken()) {
                $this->oauthMiddleware->setAccessToken($accessToken);
            }

            $this->oauthMiddleware->setTokenSaveCallback(function (AccessToken $token) {
                $this->saveToken($token);
            });

// @todo
//            if ($this->config['on_refresh_error'] !== null) {
//                $this->oauthMiddleware->setOnRefreshError($this->config['on_refresh_error']);
//            }
        }

        return $this->oauthMiddleware;
    }

    /**
     * @inheritdoc
     */
    public function setApiToken($token, $type)
    {
        $this->config['api_token'] = $token;
        if (!in_array($type, ['access', 'exchange'])) {
            throw new \InvalidArgumentException('Invalid API token type: ' . $type);
        }
        $this->config['api_token_type'] = $type;
        if (isset($this->oauthMiddleware)) {
            $this->oauthMiddleware = null;
        }
    }

    /**
     * @inheritdoc
     */
    public function getClient()
    {
        if (!isset($this->client)) {
            $stack = HandlerStack::create();
            $stack->push($this->getOauthMiddleware());

            $config = [
                'handler' => $stack,
                'headers' => ['User-Agent' => $this->config['user_agent']],
                'debug' => $this->config['debug'],
                'verify' => $this->config['verify'],
                'proxy' => $this->config['proxy'],
                'timeout' => $this->config['timeout'],
                'connect_timeout' => $this->config['connect_timeout'],
                'auth' => 'oauth2',
            ];

            if ($this->config['gzip']) {
                $config['decode_content'] = true;
                $config['headers']['Accept-Encoding'] = 'gzip';
            }

            if ($url = $this->getApiUrl()) {
                $config['base_uri'] = $url;
            }

            $this->client = new Client($config);
        }

        return $this->client;
    }
}
