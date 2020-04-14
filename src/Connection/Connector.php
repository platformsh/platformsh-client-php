<?php

namespace Platformsh\Client\Connection;

use CommerceGuys\Guzzle\Oauth2\AccessToken;
use CommerceGuys\Guzzle\Oauth2\GrantType\RefreshToken;
use CommerceGuys\Guzzle\Oauth2\Oauth2Subscriber;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Collection;
use GuzzleHttp\Exception\ClientException;
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
     *     - accounts (string): The endpoint URL for the accounts API.
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
     *     - invalid_refresh_token_callback: A callback to run when an invalid
     *       refresh token error is received. It will be passed a Guzzle
     *       BadResponseException, and should return an AccessToken or null.
     * @param SessionInterface $session
     */
    public function __construct(array $config = [], SessionInterface $session = null)
    {
        $defaults = [
          'accounts' => 'https://accounts.platform.sh/api/v1/',
          'client_id' => 'platformsh-client-php',
          'client_secret' => '',
          'debug' => false,
          'verify' => true,
          'user_agent' => null,
          'cache' => false,
          'revoke_url' => '/oauth2/revoke',
          'token_url' => '/oauth2/token',
          'proxy' => null,
          'api_token' => null,
          'api_token_type' => 'access',
          'gzip' => extension_loaded('zlib'),
          'invalid_refresh_token_callback' => null,
        ];
        $this->config = Collection::fromConfig($config, $defaults);

        if (!isset($this->config['user_agent'])) {
            $this->config['user_agent'] = $this->defaultUserAgent();
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
     * @return string
     */
    public function getAccountsEndpoint()
    {
        return $this->config['accounts'];
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
     * Revokes the access and refresh tokens saved in the session.
     */
    private function revokeTokens()
    {
        $revocations = array_filter([
            'refresh_token' => $this->session->get('refreshToken'),
            'access_token' => $this->session->get('accessToken'),
        ]);
        $url = Url::fromString($this->config['accounts'])
            ->combine($this->config['revoke_url'])
            ->__toString();
        foreach ($revocations as $type => $token) {
            $options = ['body' => [
                'client_id' => $this->config['client_id'],
                'client_secret' => $this->config['client_secret'],
                'token' => $token,
                'token_type_hint' => $type,
            ]];
            try {
                $this->getClient()->post($url, $options);
            }  catch (ClientException $e) {
                // Ignore unsupported token type errors.
                if ($e->getResponse()) {
                    $data = $e->getResponse()->json();
                    if ($data['error'] !== 'unsupported_token_type') {
                        throw $e;
                    }
                }
            }
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
    public function logIn($username, $password, $force = false, $totp = null)
    {
        if (!$force && $this->isLoggedIn() && $this->session->get('username') === $username) {
            return;
        }
        $this->logOut();
        $client = $this->getGuzzleClient([
          'base_url' => $this->config['accounts'],
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
            'token_url' => $this->config['token_url'],
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
              'base_url' => $this->config['accounts'],
              'defaults' => [
                'headers' => ['User-Agent' => $this->config['user_agent']],
                'debug' => $this->config['debug'],
                'verify' => $this->config['verify'],
                'proxy' => $this->config['proxy'],
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
                    'token_url' => $this->config['token_url'],
                  ]
                );
            }
            elseif ($this->session->get('refreshToken')) {
                $requestTokenGrantType = new RefreshToken(
                  $oauth2Client, [
                    'client_id' => $this->config['client_id'],
                    'client_secret' => $this->config['client_secret'],
                    'refresh_token' => $this->session->get('refreshToken'),
                    'token_url' => $this->config['token_url'],
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

            if ($this->config['invalid_refresh_token_callback'] !== null) {
                $this->oauth2Plugin->setInvalidRefreshTokenCallback($this->config['invalid_refresh_token_callback']);
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
                'subscribers' => [$oauth2],
                'auth' => 'oauth2',
              ],
            ];

            if ($this->config['gzip']) {
                $options['defaults']['decode_content'] = true;
                $options['defaults']['headers']['Accept-Encoding'] = 'gzip';
            }

            $client = $this->getGuzzleClient($options);

            $this->setUpCache($client);
            $this->client = $client;
        }

        return $this->client;
    }
}
