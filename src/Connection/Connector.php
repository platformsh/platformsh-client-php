<?php

namespace Platformsh\Client\Connection;

use GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache\Storage\Psr6CacheStorage;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;
use Sainsburys\Guzzle\Oauth2\AccessToken;
use Sainsburys\Guzzle\Oauth2\GrantType\RefreshToken;
use Sainsburys\Guzzle\Oauth2\Middleware\OAuthMiddleware;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Platformsh\Client\OAuth2\ApiToken;
use Platformsh\Client\OAuth2\PasswordCredentialsWithTfa;
use Platformsh\Client\Session\Session;
use Platformsh\Client\Session\SessionInterface;

class Connector implements ConnectorInterface
{
    /** @var array */
    protected $config = [];

    /** @var ClientInterface */
    protected $client;

    /** @var OAuthMiddleware|null */
    protected $oauthMiddleware;

    /** @var SessionInterface */
    protected $session;

    /** @var bool */
    protected $loggedOut = false;

    /** @var HandlerStack */
    protected $stack;

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
     *     - cache (array|bool): Caching. Set to false (the default) to disable
     *       caching. @todo implement other options
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
        $this->config = $config + $defaults;

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
        } elseif ($this->oauthMiddleware) {
            // Save the access token for future requests.
            // @todo patch the middleware to allow getting an access token without implicit refresh
            $token = $this->getOauthMiddleware()->getAccessToken(false);
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
            'base_uri' => $this->config['accounts'],
            'debug' => $this->config['debug'],
            'verify' => $this->config['verify'],
            'proxy' => $this->config['proxy'],
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
     * Get an OAuth2 middleware to add to Guzzle clients.
     *
     * @throws \RuntimeException
     *
     * @return OAuthMiddleware
     */
    protected function getOauthMiddleware()
    {
        if (!$this->oauthMiddleware) {
            if (!$this->isLoggedIn()) {
                throw new \RuntimeException('Not logged in');
            }

            // Ensure session tokens or other data are not used if an API token
            // is set.
            if ($this->config['api_token'] && $this->config['api_token_type'] === 'access') {
                $this->session->clear();
            }

            $options = [
                'base_uri' => $this->config['accounts'],
                'headers' => ['User-Agent' => $this->config['user_agent']],
                'debug' => $this->config['debug'],
                'verify' => $this->config['verify'],
                'proxy' => $this->config['proxy'],
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

            $this->oauthMiddleware = new OAuthMiddleware($oauth2Client, $requestTokenGrantType);

            // If an access token is already available (via an API token or via
            // the session) then set it in the subscriber.
            $accessToken = $this->config['api_token'] && $this->config['api_token_type'] === 'access'
              ? $this->config['api_token']
              : $this->session->get('accessToken');
            if ($accessToken) {
                $this->oauthMiddleware->setAccessToken(
                  $accessToken,
                  $this->session->get('tokenType') ?: null,
                  $this->session->get('expires') ?: null
                );
            }
        }

        return $this->oauthMiddleware;
    }

    /**
     * Set up caching on a Guzzle client.
     *
     * @param HandlerStack $stack
     */
    protected function setUpCache(HandlerStack $stack)
    {
        if ($this->config['cache'] === false) {
            return;
        }
        $options = is_array($this->config['cache']) ? $this->config['cache'] : [];
        if (empty($options['pool'])) {
            $middleware = new CacheMiddleware(new PrivateCacheStrategy(new Psr6CacheStorage($options['pool'])));
            $stack->push($middleware, 'cache');
        }
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
        if (isset($this->oauthMiddleware)) {
            $this->oauthMiddleware = null;
        }
    }

    /**
     * @return HandlerStack
     */
    private function getHandlerStack()
    {
        $this->stack = isset($this->stack) ?: HandlerStack::create();

        return $this->stack;
    }

    /**
     * @inheritdoc
     */
    public function getClient()
    {
        if (!isset($this->client)) {
            $oauth2 = $this->getOauthMiddleware();

            $stack = $this->getHandlerStack();
            $stack->push($oauth2->onBefore());
            $stack->push($oauth2->onFailure(3));
            $this->setUpCache($stack);

            $options = [
                'handler' => $stack,
                'headers' => ['User-Agent' => $this->config['user_agent']],
                'debug' => $this->config['debug'],
                'verify' => $this->config['verify'],
                'proxy' => $this->config['proxy'],
                'auth' => 'oauth2',
            ];

            // The access token might change during the request cycle, because
            // the OAuth middleware may refresh it. So we ensure the access token
            // is saved immediately after each successful request.
//            $this->stack->push(function (callable $handler) {
//                return function (RequestInterface $request, array $options) use ($handler) {
//                    if ($request->hasHeader('Authorization') {
//                        $response = $event->getResponse();
//                        if ($response && substr($response->getStatusCode(), 0, 1) === '2') {
//                            // @todo patch the middleware to allow getting an access token without implicit refresh
//                            $token = $oauth2->getAccessToken(false);
//                            if ($token !== null) {
//                                $this->saveToken($token);
//                            }
//                        }
//                    }
//
//                    return $handler($request, $options);
//                };
//            });

            $client = $this->getGuzzleClient($options);

            $this->client = $client;
        }

        return $this->client;
    }
}
