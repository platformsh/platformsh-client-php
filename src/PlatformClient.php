<?php

namespace Platformsh\Client;

use Platformsh\Client\Connection\Connector;
use Platformsh\Client\Connection\ConnectorInterface;
use Platformsh\Client\Model\Project;
use Platformsh\Client\Model\SshKey_Accounts;

class PlatformClient
{

    /** @var ConnectorInterface */
    protected $connector;

    /** @var array */
    protected $accountInfo;

    /**
     * @param ConnectorInterface $connector
     */
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
     * Get account information for the logged-in user.
     *
     * @param bool $reset
     *
     * @return array
     */
    public function getAccountInfo($reset = false)
    {
        if (!isset($this->accountInfo) || $reset) {
            $client = $this->connector->getClient();
            $this->accountInfo = (array) $client->get('me')->json();
        }

        return $this->accountInfo;
    }

    /**
     * Get the logged-in user's projects.
     *
     * @param bool $reset
     *
     * @return Project[]
     */
    public function getProjects($reset = false)
    {
        $data = $this->getAccountInfo($reset);
        $projects = [];
        foreach ($data['projects'] as $project) {
            // Each project has its own endpoint on a Platform.sh cluster.
            $client = $this->connector->getClient($project['endpoint']);
            // @todo get the actual ID added to the API
            $projectId = basename($project['endpoint']);
            $projects[$projectId] = new Project($project, $client);
        }

        return $projects;
    }

    /**
     * Get a single project by its ID.
     *
     * @param string $id
     * @param string $endpoint
     *
     * @return Project|false
     */
    public function getProject($id, $endpoint = null)
    {
        if ($endpoint !== null) {
            $project = $this->getProjectDirect($id, $endpoint);
        } else {
            $projects = $this->getProjects();
            if (!isset($projects[$id])) {
                return false;
            }
            $project = $projects[$id];
        }

        return $project;
    }

    /**
     * Get a single project with a known endpoint.
     *
     * @param string $id
     * @param string $endpoint
     *
     * @return Project|false
     */
    protected function getProjectDirect($id, $endpoint)
    {
        return Project::get($id, $endpoint, $this->connector->getClient($endpoint));
    }

    /**
     * Get the logged-in user's SSH keys.
     *
     * @param bool $reset
     *
     * @return SshKey_Accounts[]
     */
    public function getSshKeys($reset = false)
    {
        $data = $this->getAccountInfo($reset);

        return SshKey_Accounts::wrapCollection($data['ssh_keys'], $this->connector->getClient());
    }

    /**
     * Add an SSH public key to the logged-in user's account.
     *
     * @param string $value The SSH key value.
     * @param string $title A title for the key (optional).
     *
     * @return SshKey_Accounts
     */
    public function addSshKey($value, $title = null)
    {
        $values = ['value' => $value];
        if ($title) {
            $values['title'] = $title;
        }

        return SshKey_Accounts::create($values, 'ssh_keys', $this->connector->getClient());
    }
}
