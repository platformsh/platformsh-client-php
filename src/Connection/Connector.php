<?php

namespace Platformsh\Client\Connection;

use CommerceGuys\Guzzle\Oauth2\AccessToken;
use CommerceGuys\Guzzle\Oauth2\GrantType\RefreshToken;
use CommerceGuys\Guzzle\Oauth2\Oauth2Subscriber;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Collection;
use GuzzleHttp\Event\CompleteEvent;
use GuzzleHttp\Subscriber\Cache\CacheSubscriber;
use Platformsh\Client\OAuth2\ApiToken;
use Platformsh\Client\OAuth2\PasswordCredentialsWithTfa;
use Platformsh\Client\Session\Session;
use Platformsh\Client\Session\SessionInterface;

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

    /** @var bool */
    protected $loggedOut = false;

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
     * @param SessionInterface $session
     */
    public function __construct(array $config = [], SessionInterface $session = null)
    {
        $version = '0.1.x';
        $url = 'https://github.com/platformsh/platformsh-client-php';

        $defaults = [
          'accounts' => 'https://accounts.platform.sh/api/platform/',
          'client_id' => 'platformsh-client-php',
          'client_secret' => '',
          'debug' => false,
          'verify' => true,
          'user_agent' => "Platform.sh-Client-PHP/$version (+$url)",
          'cache' => false,
          'token_url' => '/oauth2/token',
          'proxy' => null,
          'api_token' => null,
          'api_token_type' => 'access',
        ];
        $this->config = Collection::fromConfig($config, $defaults);

        $this->session = $session ?: new Session();
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
        $this->loggedOut = true;
        $this->session->clear();
        $this->session->save();
    }

    public function __destruct()
    {
        if ($this->loggedOut) {
            $this->session->clear();
        } elseif ($this->oauth2Plugin) {
            // Save the access token for future requests.
            $token = $this->getOauth2Plugin()->getAccessToken();
            if ($token !== null) {
                $this->saveToken($token);
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
        $this->loggedOut = false;
        if (!$force && $this->isLoggedIn() && $this->session->get('username') === $username) {
            return;
        }
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
    protected function saveToken(AccessToken $token)
    {
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
            $refreshTokenGrantType = null;
            if ($this->config['api_token'] && $this->config['api_token_type'] !== 'access') {
                $refreshTokenGrantType = new ApiToken(
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
                $refreshTokenGrantType = new RefreshToken(
                  $oauth2Client, [
                    'client_id' => $this->config['client_id'],
                    'client_secret' => $this->config['client_secret'],
                    'refresh_token' => $this->session->get('refreshToken'),
                    'token_url' => $this->config['token_url'],
                  ]
                );
            }
            $this->oauth2Plugin = new Oauth2Subscriber(null, $refreshTokenGrantType);
            if ($this->session->get('accessToken') || ($this->config['api_token'] && $this->config['api_token_type'] === 'access')) {
                $type = $this->session->get('tokenType');
                // If the token does not expire, the 'expires' time must be null.
                $expires = $this->session->get('expires') ?: null;
                $this->oauth2Plugin->setAccessToken($this->session->get('accessToken') ?: $this->config['api_token'], $type, $expires);
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
            $client = $this->getGuzzleClient($options);

            // The access token might change during the request cycle, because
            // the OAuth2Subscriber may refresh it. So we ensure the access
            // token is saved immediately after each successful request.
            $client->getEmitter()->on('complete', function (CompleteEvent $event) use ($oauth2) {
                if ($event->getRequest()->getConfig()->get('auth') !== 'oauth2') {
                    return;
                }
                $response = $event->getResponse();
                if ($response && substr($response->getStatusCode(), 0, 1) === '2') {
                    $token = $oauth2->getAccessToken();
                    if ($token !== null) {
                        $this->saveToken($token);
                    }
                }
            });

            $this->setUpCache($client);
            $this->client = $client;
        }

        return $this->client;
    }
}
