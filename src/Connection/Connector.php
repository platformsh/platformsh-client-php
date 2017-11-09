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
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

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
          'accounts' => 'https://accounts.platform.sh/api/platform/',
          'client_id' => 'platformsh-client-php',
          'client_secret' => '',
          'debug' => false,
          'verify' => true,
          'user_agent' => "Platform.sh-Client-PHP/$version (+$url)",
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
            // @todo make sure the following work
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
        $this->session->set('token', $token);
        $this->session->save();
    }

    /**
     * @return AccessToken|null
     */
    protected function loadToken()
    {
        $token = $this->session->get('token');

        return $token ? new AccessToken($token) : null;
    }

    /**
     * @inheritdoc
     */
    public function isLoggedIn()
    {
        return $this->session->get('token') || $this->config['api_token'];
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

            // Ensure session tokens or other data are not used if an API token
            // is set.
            if ($this->config['api_token'] && $this->config['api_token_type'] === 'access') {
                $this->session->clear();
            }

            $grant = new ClientCredentials();
            $grantOptions = [];

            // Set up the "exchange" (normal) API token type.
            if ($this->config['api_token'] && $this->config['api_token_type'] !== 'access') {
                $grant = new ApiToken();
                $grantOptions['api_token'] = $this->config['api_token'];
            }

            $this->oauthMiddleware = new GuzzleMiddleware($this->getProvider(), $grant, $grantOptions);

            // If an access token is already available (via an API token or via
            // the session) then set it in the subscriber.
            $accessToken = $this->config['api_token'] && $this->config['api_token_type'] === 'access'
              ? new AccessToken(['access_token' => $this->config['api_token']])
              : $this->loadToken();
            if ($accessToken) {
                $this->oauthMiddleware->setAccessToken($accessToken);
            }
        }

        return $this->oauthMiddleware;
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
     * @inheritdoc
     */
    public function getClient()
    {
        if (!isset($this->client)) {
            $stack = HandlerStack::create();
            $stack->push($this->getOauthMiddleware());

            // The access token might change during the request cycle, because
            // the OAuth middleware may refresh it. So we ensure the access token
            // is saved immediately after each successful request.
            $stack->push(function (callable $next) {
                return function (RequestInterface $request, array $options) use ($next) {
                    return $next($request, $options)->then(function (ResponseInterface $response) {
                        if ($response && substr($response->getStatusCode(), 0, 1) === '2') {
                            $middleware = $this->getOauthMiddleware();
                            $token = $middleware->getAccessToken(false);
                            if ($token !== null) {
                                $this->saveToken($token);
                            }
                        }

                        return $response;
                    });
                };
            });

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
