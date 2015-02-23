<?php

namespace Platformsh\Client\Connection;

use CommerceGuys\Guzzle\Oauth2\GrantType\PasswordCredentials;
use CommerceGuys\Guzzle\Oauth2\GrantType\RefreshToken;
use CommerceGuys\Guzzle\Oauth2\Oauth2Subscriber;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Collection;
use GuzzleHttp\Exception\BadResponseException;
use Platformsh\Client\Session\Session;
use Platformsh\Client\Session\SessionInterface;

class Connector implements ConnectorInterface
{

    /** @var Collection */
    protected $config;

    protected $clientPrototype;
    protected $clients;
    protected $oauth2Plugin;
    protected $session;
    protected $loggedOut = false;

    /**
     * @param array            $config
     * @param SessionInterface $session
     * @param ClientInterface  $clientPrototype
     */
    public function __construct(array $config = [], SessionInterface $session = null, ClientInterface $clientPrototype = null)
    {
        $version = '0.x-dev';
        $url = 'https://github.com/platformsh/platformsh-client-php';

        $defaults = [
            'accounts' => 'https://marketplace.commerceguys.com/api/platform/',
            'client_id' => 'platformsh-client-php',
            'debug' => false,
            'verify' => true,
            'user_agent' => "Platform.sh-Client-PHP/$version (+$url)",
        ];
        $this->config = Collection::fromConfig($config, $defaults);

        $this->clientPrototype = $clientPrototype ?: new Client();
        $this->session = $session ?: new Session();
    }

    public function setDebug($debug)
    {
        $this->config['debug'] = $debug;
    }

    public function setVerifySsl($verifySsl)
    {
        $this->config['verify'] = $verifySsl;
    }

    public function logOut()
    {
        $this->session->clear();
        $this->loggedOut = true;
    }

    public function __destruct()
    {
        if ($this->loggedOut) {
            $this->session->clear();
        } elseif ($this->oauth2Plugin) {
            // Save the access token for future requests.
            $token = $this->getOauth2Plugin()->getAccessToken();
            $this->session->add([
              'accessToken' => $token->getToken(),
              'expires' => $token->getExpires()->getTimestamp(),
            ]);
        }
    }

    /**
     * @return Session
     */
    public function getSession()
    {
        return $this->session;
    }

    public function logIn($username, $password, $force = false)
    {
        $this->loggedOut = false;
        if (!$force && $this->session->get('username') === $username) {
            return;
        }
        $client = clone $this->clientPrototype;
        $client->__construct(
          [
            'base_url' => $this->config['accounts'],
            'defaults' => [
              'debug' => $this->config['debug'],
              'verify' => $this->config['verify'],
            ],
          ]
        );
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
                throw new \Exception("Invalid credentials. Please check your username/password combination");
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
     * @throws \Exception
     *
     * @return Oauth2Subscriber
     */
    protected function getOauth2Plugin()
    {
        if (!$this->oauth2Plugin) {
            if (!$this->session->get('accessToken') || !$this->session->get('refreshToken')) {
                throw new \Exception('Not logged in (no access token available)');
            }
            $options = [
              'base_url' => $this->config['accounts'],
              'defaults' => [
                'headers' => ['User-Agent' => $this->config['user_agent']],
                'debug' => $this->config['debug'],
                'verify' => $this->config['verify'],
              ],
            ];
            $oauth2Client = new Client($options);
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
            $client = clone $this->clientPrototype;
            $client->__construct($options);
            $this->clients[$endpoint] = $client;
        }

        return $this->clients[$endpoint];
    }
}
