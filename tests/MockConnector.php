<?php

namespace Platformsh\Client\Tests;

use GuzzleHttp\ClientInterface;
use Platformsh\Client\Connection\Connector;

class MockConnector extends Connector
{

    protected array $mockValues = [];
    protected int $mockStatus = 200;

    /**
     * Set the response that all future API calls should return.
     *
     * @param array $values The response body, which will be encoded as JSON.
     * @param int $status The HTTP status code.
     */
    public function setMockResult(array $values, int $status = 200): void
    {
        $this->mockValues = $values;
        $this->mockStatus = $status;

        $this->client = null;
    }

    /**
     * @inheritdoc
     */
    public function isLoggedIn(): bool
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
    public function getClient(): ClientInterface
    {
        return MockClient::create([
            'mockStatus' => $this->mockStatus,
            'mockValues' => $this->mockValues,
        ]);
    }
}
