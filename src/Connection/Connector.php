<?php

namespace Platformsh\Client\Connection;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use League\OAuth2\Client\Grant\ClientCredentials;
use League\OAuth2\Client\Grant\Password;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Token\AccessToken;
use Platformsh\Client\Session\Session;
use Platformsh\Client\Session\SessionInterface;
use Platformsh\OAuth2\Client\Provider\Platformsh;
use Platformsh\OAuth2\Client\Grant\ApiToken;
use Platformsh\OAuth2\Client\GuzzleMiddleware;

class Connector implements ConnectorInterface
{
    /** @var array */
    protected $config = [];

    /** @var ClientInterface */
    protected $client;

    /** @var callable|null */
    protected $oauthMiddleware;

    /** @var AbstractProvider */
    protected $provider;

    /** @var SessionInterface */
    protected $session;

    /** @var bool */
    protected $loggedOut = false;

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
     *     - accounts (string): The endpoint URL for the accounts API.
     *     - client_id (string): The OAuth2 client ID for this client.
     *     - debug (bool): Whether or not Guzzle debugging should be enabled
     *       (default: false).
     *     - verify (bool): Whether or not SSL verification should be enabled
     *       (default: true).
     *     - user_agent (string): The HTTP User-Agent for API requests.
     *     - proxy (array|string): A proxy setting, passed to Guzzle directly.
     *       Use a string to specify an HTTP proxy, or an array to specify
     *       different proxies for different protocols.
     * @param SessionInterface $session
     */
    public function __construct(array $config = [], SessionInterface $session = null)
    {
        $version = '2.0.x';
        $url = 'https://github.com/platformsh/platformsh-client-php';

        $defaults = [
          'accounts' => 'https://accounts.platform.sh/api/v1/',
          'client_id' => 'platformsh-client-php',
          'client_secret' => '',
          'debug' => false,
          'verify' => true,
          'user_agent' => "Platform.sh-Client-PHP/$version (+$url)",
          'token_url' => '/oauth2/token',
          'proxy' => null,
          'api_token' => null,
          'api_token_type' => 'exchange',
        ];
        $this->config = $config + $defaults;

        $this->session = $session ?: new Session();
    }

    /**
     * @inheritdoc
     */
    public function logOut()
    {
        $this->loggedOut = true;
        $this->session->clear();
        $this->session->save();
    }

    public function __destruct()
    {
        if ($this->loggedOut) {
            $this->session->clear();
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
     * {@inheritdoc}
     */
    public function getAccountsEndpoint()
    {
        return $this->config['accounts'];
    }

    /**
     * {@inheritdoc}
     */
    public function logIn($username, $password, $force = false, $totp = null)
    {
        $this->loggedOut = false;
        if (!$force && $this->isLoggedIn() && $this->session->get('username') === $username) {
            return;
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
        return $this->provider ? $this->provider : new Platformsh([
          'clientId' => $this->config['client_id'],
          'clientSecret' => $this->config['client_secret'],
          'base_uri' => $this->config['accounts'],
          'debug' => $this->config['debug'],
          'verify' => $this->config['verify'],
          'proxy' => $this->config['proxy'],
        ]);
    }

    /**
     * Save an access token to the session.
     *
     * @param AccessToken $token
     */
    protected function saveToken(AccessToken $token)
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

            $this->client = new Client([
              'handler' => $stack,
              'headers' => ['User-Agent' => $this->config['user_agent']],
              'debug' => $this->config['debug'],
              'verify' => $this->config['verify'],
              'proxy' => $this->config['proxy'],
              'auth' => 'oauth2',
            ]);
        }

        return $this->client;
    }
}
