<?php

namespace Platformsh\Client\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Ring\Client\MockHandler;
use Platformsh\Client\Connection\Connector;

class MockConnector extends Connector
{

    protected $expectedValues = [];
    protected $expectedStatus = 200;

    /**
     * @param array $values
     * @param int   $status
     */
    public function setExpectedResult(array $values, $status = 200)
    {
        $this->expectedValues = $values;
        $this->expectedStatus = $status;
    }

    /**
     * @inheritdoc
     */
    public function isLoggedIn()
    {
        // @todo test the login method
        return true;
    }

    /**
     * @inheritdoc
     *
     * Ensure the OAuth2 client will always receive a successful token
     * response.
     */
    protected function getGuzzleClient(array $options)
    {
        $handler = new MockHandler(
          [
            'status' => $this->expectedStatus,
            'body' => json_encode($this->expectedValues),
          ]
        );
        return new Client(['handler' => $handler] + $options);
    }

    /**
     * @inheritdoc
     *
     * Ensure the OAuth2 client will always receive a successful token
     * response.
     */
    protected function getOauth2Client(array $options)
    {
        $handler = new MockHandler(
          [
            'status' => 200,
            'body' => json_encode([
              'token_type' => 'bearer',
              'expires_in' => 3600,
              'access_token' => 'test',
              'refresh_token' => 'test',
            ]),
          ]
        );
        return new Client(['handler' => $handler] + $options);
    }
}
