<?php

namespace Platformsh\Client\Connection;

use CommerceGuys\Guzzle\Oauth2\GrantType\PasswordCredentials;
use CommerceGuys\Guzzle\Oauth2\GrantType\RefreshToken;
use CommerceGuys\Guzzle\Oauth2\Oauth2Subscriber;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Collection;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Subscriber\Cache\CacheSubscriber;
use Platformsh\Client\Session\Session;
use Platformsh\Client\Session\SessionInterface;

class Connector implements ConnectorInterface
{

    /** @var Collection */
    protected $config;

    /** @var ClientInterface[] */
    protected $clients = [];

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
     * @param SessionInterface $session
     */
    public function __construct(array $config = [], SessionInterface $session = null)
    {
        $version = '0.1.0-beta1';
        $url = 'https://github.com/platformsh/platformsh-client-php';

        $defaults = [
          'accounts' => 'https://marketplace.commerceguys.com/api/platform/',
          'client_id' => 'platformsh-client-php',
          'debug' => false,
          'verify' => true,
          'user_agent' => "Platform.sh-Client-PHP/$version (+$url)",
          'cache' => false,
        ];
        $this->config = Collection::fromConfig($config, $defaults);

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
        } elseif ($this->oauth2Plugin) {
            // Save the access token for future requests.
            $token = $this->getOauth2Plugin()->getAccessToken();
            $this->session->add(
              [
                'accessToken' => $token->getToken(),
                'expires' => $token->getExpires()->getTimestamp(),
              ]
            );
        }
        $this->session->save();
    }

    /**
     * @inheritdoc
     */
    public function getSession()
    {
        return $this->session;
    }

    /**
     * @inheritdoc
     */
    public function logIn($username, $password, $force = false)
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
          ],
        ]);
        $grantType = new PasswordCredentials(
          $client, [
            'client_id' => $this->config['client_id'],
            'username' => $username,
            'password' => $password,
          ]
        );
        try {
            $token = $grantType->getToken();
        } catch (BadResponseException $e) {
            $response = $e->getResponse();
            if ($response && $response->getStatusCode() === 401) {
                throw new \InvalidArgumentException("Invalid credentials. Please check your username/password combination");
            }
            throw $e;
        }
        $this->session->add(
          [
            'username' => $username,
            'accessToken' => $token->getToken(),
            'tokenType' => $token->getType(),
            'expires' => $token->getExpires()->getTimestamp(),
            'refreshToken' => $token->getRefreshToken()->getToken(),
          ]
        );
        $this->session->save();
    }

    /**
     * @inheritdoc
     */
    public function isLoggedIn()
    {
        return $this->session->get('accessToken') && $this->session->get('refreshToken');
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
              ],
            ];
            $oauth2Client = $this->getOauth2Client($options);
            $refreshTokenGrantType = new RefreshToken(
              $oauth2Client, [
                'client_id' => $this->config['client_id'],
                'refresh_token' => $this->session->get('refreshToken'),
              ]
            );
            $this->oauth2Plugin = new Oauth2Subscriber(null, $refreshTokenGrantType);
            if ($this->session->get('accessToken')) {
                $expiresIn = $this->session->get('expires');
                $type = $this->session->get('tokenType');
                $this->oauth2Plugin->setAccessToken($this->session->get('accessToken'), $type, $expiresIn);
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
    public function getClient($endpoint = null)
    {
        $endpoint = $endpoint ?: $this->config['accounts'];
        if (!isset($this->clients[$endpoint])) {
            $options = [
              'base_url' => $endpoint,
              'defaults' => [
                'headers' => ['User-Agent' => $this->config['user_agent']],
                'debug' => $this->config['debug'],
                'verify' => $this->config['verify'],
                'subscribers' => [$this->getOauth2Plugin()],
                'auth' => 'oauth2',
              ],
            ];
            $client = $this->getGuzzleClient($options);
            $this->setUpCache($client);
            $this->clients[$endpoint] = $client;
        }

        return $this->clients[$endpoint];
    }
}
