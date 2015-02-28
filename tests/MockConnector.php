<?php

namespace Platformsh\Client\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Ring\Client\MockHandler;
use Platformsh\Client\Connection\Connector;

class MockConnector extends Connector
{

    protected $mockValues = [];
    protected $mockStatus = 200;

    /**
     * Set the response that all future API calls should return.
     *
     * @param array $values The response body, which will be encoded as JSON.
     * @param int   $status The HTTP status code.
     */
    public function setMockResult(array $values, $status = 200)
    {
        $this->mockValues = $values;
        $this->mockStatus = $status;

        // Empty the cache of client objects.
        $this->clients = [];
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
     * Add a mock handler so that API responses will be intercepted with the
     * mockStatus and mockValues properties.
     */
    protected function getGuzzleClient(array $options)
    {
        return $this->getMockClient($this->mockValues, $this->mockStatus, $options);
    }

    /**
     * @inheritdoc
     *
     * Ensure the OAuth2 client will always receive a successful token
     * response.
     */
    protected function getOauth2Client(array $options)
    {
        $values = [
          'token_type' => 'bearer',
          'expires_in' => 3600,
          'access_token' => 'test',
          'refresh_token' => 'test',
        ];

        return $this->getMockClient($values, 200, $options);
    }

    /**
     * @param array $values
     * @param int   $status
     * @param array $options
     *
     * @return \GuzzleHttp\ClientInterface
     */
    protected function getMockClient($values, $status, array $options = [])
    {
        $handler = new MockHandler([
          'status' => $status,
          'body' => json_encode($values),
        ]);

        return new Client(['handler' => $handler] + $options);
    }
}
