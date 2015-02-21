<?php

namespace Platformsh\Client\Connection;

use CommerceGuys\Guzzle\Oauth2\GrantType\PasswordCredentials;
use CommerceGuys\Guzzle\Oauth2\GrantType\RefreshToken;
use CommerceGuys\Guzzle\Oauth2\Oauth2Subscriber;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Platformsh\Client\Session\Session;
use Platformsh\Client\Session\SessionInterface;

class Connector implements ConnectorInterface
{

    const CLIENT_ID = 'platform-cli';

    protected $clientPrototype;
    protected $clients;
    protected $debug = false;
    protected $verifySsl = true;
    protected $oauth2Plugin;
    protected $session;

    /**
     * @param string           $accountsEndpoint
     * @param SessionInterface $session
     * @param ClientInterface  $clientPrototype
     */
    public function __construct($accountsEndpoint = '', SessionInterface $session = null, ClientInterface $clientPrototype = null)
    {
        $this->clientPrototype = $clientPrototype ?: new Client();
        $this->accountsEndpoint = $accountsEndpoint ?: 'https://marketplace.commerceguys.com/api/platform/';

        $this->session = $session ?: new Session();
    }

    public function setDebug($debug)
    {
        $this->debug = $debug;
    }

    public function __destruct()
    {
        if ($this->oauth2Plugin) {
            // Save the access token for future requests.
            $token = $this->getOauth2Plugin()
                          ->getAccessToken();
            $this->session->set('accessToken', $token->getToken());
            $this->session->set(
              'expires',
              $token->getExpires()
                    ->getTimestamp()
            );
        }
    }

    /**
     * @return Session
     */
    public function getSession()
    {
        return $this->session;
    }

    /**
     * Get an HTTP User Agent string representing this application.
     *
     * @return string
     */
    protected function getUserAgent()
    {
        $version = '0.x-dev';
        $url = 'https://github.com/platformsh/platformsh-client-php';

        return "Platform.sh-Client/$version (+$url)";
    }

    public function authenticate($username, $password, $force = false)
    {
        if (!$force && $this->session->get('username') === $username) {
            return;
        }
        $client = clone $this->clientPrototype;
        $client->__construct(['base_url' => $this->accountsEndpoint]);
        $grantType = new PasswordCredentials(
          $client, [
            'client_id' => self::CLIENT_ID,
            'username' => $username,
            'password' => $password,
          ]
        );
        $token = $grantType->getToken();
        $this->session->set('username', $username);
        $this->session->set('accessToken', $token->getToken());
        $this->session->set('tokenType', $token->getType());
        $this->session->set('expires', $token->getExpires()->getTimestamp());
        $this->session->set('refreshToken', $token->getRefreshToken()->getToken());
    }

    /**
     * @throws \Exception
     *
     * @return Oauth2Subscriber
     */
    protected function getOauth2Plugin()
    {
        if (!$this->oauth2Plugin) {
            $options = [
              'base_url' => $this->accountsEndpoint,
              'defaults' => [
                'headers' => ['User-Agent' => $this->getUserAgent()],
                'debug' => $this->debug,
              ],
              'verify' => $this->verifySsl,
            ];
            $oauth2Client = new Client($options);
            $refreshTokenGrantType = new RefreshToken($oauth2Client, [
              'client_id' => self::CLIENT_ID,
              'refresh_token' => $this->session->get('refreshToken'),
            ]);
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
        $endpoint = $endpoint ?: $this->accountsEndpoint;
        if (!isset($this->clients[$endpoint])) {
            $options = [
              'base_url' => $endpoint,
              'defaults' => [
                'headers' => ['User-Agent' => $this->getUserAgent()],
                'debug' => $this->debug,
                'verify' => $this->verifySsl,
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
