<?php

namespace Platformsh\Client;

use Platformsh\Client\Connection\Connector;
use Platformsh\Client\Connection\ConnectorInterface;
use Platformsh\Client\Model\Project;

class PlatformClient
{

    /** @var ConnectorInterface */
    protected $connector;

    /** @var array */
    protected $accountInfo;

    public function __construct(ConnectorInterface $connector = null)
    {
        $this->connector = $connector ?: new Connector();
    }

    /**
     * @return ConnectorInterface
     */
    public function getConnector()
    {
        return $this->connector;
    }

    /**
     * @param bool $reset
     *
     * @return array
     */
    public function getAccountInfo($reset = false)
    {
        if (!isset($this->accountInfo) || $reset) {
            $client = $this->getConnector()->getAccountsClient();
            $this->accountInfo = (array) $client->get('me')->json();
        }
        return $this->accountInfo;
    }

    /**
     * @param bool $reset
     *
     * @return Project[]
     */
    public function getProjects($reset = false)
    {
        $client = $this->getConnector()->getAccountsClient();
        return array_map(
          function ($element) use ($client) {
              return new Project($element, $client);
          },
          $this->getAccountInfo($reset)['projects']
        );
    }
}
