<?php

namespace Platformsh\Client\Connection;

use CommerceGuys\Guzzle\Oauth2\AccessToken;
use CommerceGuys\Guzzle\Oauth2\GrantType\RefreshToken;
use CommerceGuys\Guzzle\Oauth2\Oauth2Subscriber;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Collection;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Subscriber\Cache\CacheSubscriber;
use GuzzleHttp\Url;
use Platformsh\Client\OAuth2\ApiToken;
use Platformsh\Client\OAuth2\PasswordCredentialsWithTfa;
use Platformsh\Client\Session\Session;
use Platformsh\Client\Session\SessionInterface;
use Platformsh\Client\Session\Storage\File;

class Connector implements ConnectorInterface
{

    /** @var Collection */
    protected $config;

    /** @var ClientInterface */
    protected $client;

    /** @var Oauth2Subscriber|null */
    protected $oauth2Plugin;

    /** @var SessionInterface */
    protected $session;

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
     *     - debug (bool): Whether or not Guzzle debugging should be enabled
     *       (default: false).
     *     - verify (bool): Whether or not SSL verification should be enabled
     *       (default: true).
     *     - user_agent (string): The HTTP User-Agent for API requests.
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
          'revoke_url' => '',
          'token_url' => '',
          'certifier_url' => '',
          'centralized_permissions_enabled' => false,
          'strict_project_references' => false,
          'proxy' => null,
          'timeout' => 60.0,
          'connect_timeout' => 60.0,
          'api_token' => null,
          'api_token_type' => 'access',
          'gzip' => extension_loaded('zlib'),
          'on_refresh_start' => null,
          'on_refresh_end' => null,
          'on_refresh_error' => null,
        ];
        $this->config = Collection::fromConfig($config, $defaults);

        if (!isset($this->config['user_agent'])) {
            $this->config['user_agent'] = $this->defaultUserAgent();
        }

        if (!empty($this->config['auth_url'])) {
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

    /**
     * @return Collection
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
        $version = trim(file_get_contents(__DIR__ . '/../../version.txt')) ?: '0.x.x';

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
     * @deprecated Use ConnectorInterface::getApiUrl() instead
     *
     * @see ConnectorInterface::getApiUrl()
     *
     * @return string
     */
    public function getAccountsEndpoint()
    {
        return $this->config['accounts'];
    }

    /**
     * {@inheritDoc}
     */
    public function getApiUrl()
    {
        return rtrim($this->config['api_url'], '/');
    }

    /**
     * @inheritdoc
     */
    public function logOut()
    {
        $this->oauth2Plugin = null;

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
            $url = Url::fromString($this->config['accounts'])
                ->combine($this->config[$key])
                ->__toString();
        }

        return $url;
    }

    /**
     * Revokes the access and refresh tokens saved in the session.
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
                'body' => [
                    'client_id' => $this->config['client_id'],
                    'client_secret' => $this->config['client_secret'],
                    'token' => $token,
                    'token_type_hint' => $type,
                ],
                'auth' => false,
            ];
            $this->getClient()->post($url, $options);
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
     */
    public function logIn($username, $password, $force = false, $totp = null)
    {
        if (!$force && $this->isLoggedIn() && $this->session->get('username') === $username) {
            return;
        }
        $this->logOut();
        $client = $this->getGuzzleClient([
          'defaults' => [
            'debug' => $this->config['debug'],
            'verify' => $this->config['verify'],
            'proxy' => $this->config['proxy'],
          ],
        ]);
        $grantType = new PasswordCredentialsWithTfa(
          $client, [
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'username' => $username,
            'password' => $password,
            'token_url' => $this->getOAuthUrl('token_url'),
          ]
        );
        if (isset($totp)) {
            $grantType->setTotp($totp);
        }
        $token = $grantType->getToken();
        $this->session->set('username', $username);
        $this->saveToken($token);
    }

    /**
     * Save an access token to the session.
     *
     * @param AccessToken $token
     */
    public function saveToken(AccessToken $token)
    {
        if ($this->config['api_token'] && $this->config['api_token_type'] === 'access') {
            return;
        }
        $this->session->set('accessToken', $token->getToken());
        $this->session->set('tokenType', $token->getType());
        if ($token->getExpires()) {
            $this->session->set('expires', $token->getExpires()->getTimestamp());
        }
        if ($token->getRefreshToken()) {
            $this->session->set('refreshToken', $token->getRefreshToken()->getToken());
        }
        $this->session->save();
    }

    /**
     * @inheritdoc
     */
    public function isLoggedIn()
    {
        return $this->session->get('accessToken')
        || $this->session->get('refreshToken')
        || $this->config['api_token'];
    }

    /**
     * @param array $options
     *
     * @return ClientInterface
     */
    protected function getGuzzleClient(array $options)
    {
        return new Client($options);
    }

    /**
     * @param array $options
     *
     * @return ClientInterface
     */
    protected function getOauth2Client(array $options)
    {
        return $this->getGuzzleClient($options);
    }

    /**
     * Get an OAuth2 subscriber to add to Guzzle clients.
     *
     * @throws \RuntimeException
     *
     * @return Oauth2Subscriber
     */
    protected function getOauth2Plugin()
    {
        if (!$this->oauth2Plugin) {
            if (!$this->isLoggedIn()) {
                throw new \RuntimeException('Not logged in');
            }

            // Ensure session tokens or other data are not used if an API token
            // is set.
            if ($this->config['api_token'] && $this->config['api_token_type'] === 'access') {
                $this->session->clear();
            }

            $options = [
              'defaults' => [
                'headers' => ['User-Agent' => $this->config['user_agent']],
                'debug' => $this->config['debug'],
                'verify' => $this->config['verify'],
                'proxy' => $this->config['proxy'],
                'timeout' => $this->config['timeout'],
                'connect_timeout' => $this->config['connect_timeout'],
              ],
            ];
            $oauth2Client = $this->getOauth2Client($options);
            $requestTokenGrantType = null;
            if ($this->config['api_token'] && $this->config['api_token_type'] !== 'access') {
                $requestTokenGrantType = new ApiToken(
                  $oauth2Client, [
                    'client_id' => $this->config['client_id'],
                    'client_secret' => $this->config['client_secret'],
                    'api_token' => $this->config['api_token'],
                    'refresh_token' => $this->session->get('refreshToken'),
                    'token_url' => $this->getOAuthUrl('token_url'),
                  ]
                );
            }
            elseif ($this->session->get('refreshToken')) {
                $requestTokenGrantType = new RefreshToken(
                  $oauth2Client, [
                    'client_id' => $this->config['client_id'],
                    'client_secret' => $this->config['client_secret'],
                    'refresh_token' => $this->session->get('refreshToken'),
                    'token_url' => $this->getOAuthUrl('token_url'),
                  ]
                );
            }

            $this->oauth2Plugin = new Oauth2Subscriber(null, $requestTokenGrantType);

            // If an access token is already available (via an API token or via
            // the session) then set it in the subscriber.
            $accessToken = $this->config['api_token'] && $this->config['api_token_type'] === 'access'
              ? $this->config['api_token']
              : $this->session->get('accessToken');
            if ($accessToken) {
                $this->oauth2Plugin->setAccessToken(
                  $accessToken,
                  $this->session->get('tokenType') ?: null,
                  $this->session->get('expires') ?: null
                );
            }

            $this->oauth2Plugin->setTokenSaveCallback(function (AccessToken $token) {
                $this->saveToken($token);
            });

            if ($this->config['on_refresh_start'] !== null) {
                $this->oauth2Plugin->setOnRefreshStart($this->config['on_refresh_start']);
            }
            if ($this->config['on_refresh_end'] !== null) {
                $this->oauth2Plugin->setOnRefreshEnd($this->config['on_refresh_end']);
            }
            if ($this->config['on_refresh_error'] !== null) {
                $this->oauth2Plugin->setOnRefreshError($this->config['on_refresh_error']);
            }
        }

        return $this->oauth2Plugin;
    }

    /**
     * Set up caching on a Guzzle client.
     *
     * @param ClientInterface $client
     */
    protected function setUpCache(ClientInterface $client)
    {
        if ($this->config['cache'] === false) {
            return;
        }
        $options = is_array($this->config['cache']) ? $this->config['cache'] : [];
        CacheSubscriber::attach($client, $options);
    }

    /**
     * @inheritdoc
     */
    public function setApiToken($token, $type = 'access')
    {
        $this->config['api_token'] = $token;
        if ($type !== null) {
            if (!in_array($type, ['access', 'exchange'])) {
                throw new \InvalidArgumentException('Invalid API token type: ' . $type);
            }
            $this->config['api_token_type'] = $type;
        }
        if (isset($this->oauth2Plugin)) {
            $this->oauth2Plugin = null;
        }
    }

    /**
     * @inheritdoc
     */
    public function getClient()
    {
        if (!isset($this->client)) {
            $oauth2 = $this->getOauth2Plugin();
            $options = [
              'defaults' => [
                'headers' => ['User-Agent' => $this->config['user_agent']],
                'debug' => $this->config['debug'],
                'verify' => $this->config['verify'],
                'proxy' => $this->config['proxy'],
                'timeout' => $this->config['timeout'],
                'connect_timeout' => $this->config['connect_timeout'],
                'subscribers' => [$oauth2],
                'auth' => 'oauth2',
              ],
            ];

            if ($this->config['gzip']) {
                $options['defaults']['decode_content'] = true;
                $options['defaults']['headers']['Accept-Encoding'] = 'gzip';
            }

            if ($url = $this->getApiUrl()) {
                $options['base_url'] = $url;
            }

            $client = $this->getGuzzleClient($options);

            $this->setUpCache($client);
            $this->client = $client;
        }

        return $this->client;
    }
}
