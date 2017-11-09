<?php

namespace Platformsh\Client\Tests;

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

        $this->client = null;
    }

    /**
     * @inheritdoc
     */
    public function isLoggedIn()
    {
        $this->session->set('refreshToken', 'test');
        // @todo test the login method
        return true;
    }

    /**
     * @inheritdoc
     *
     * Add a mock handler so that API responses will be intercepted with the
     * mockStatus and mockValues properties.
     */
    public function getClient()
    {
        return $this->getMockClient($this->mockValues, $this->mockStatus);
    }

    /**
     * @param array $values
     * @param int   $status
     * @param array $options
     *
     * @return \GuzzleHttp\ClientInterface
     */
    private function getMockClient($values, $status, array $options = [])
    {
        return new MockClient(
            [
                'mockStatus' => $status,
                'mockValues' => $values,
            ] + $options
        );
    }
}
