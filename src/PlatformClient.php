<?php

namespace Platformsh\Client;

use Platformsh\Client\Connection\Connector;
use Platformsh\Client\Connection\ConnectorInterface;
use Platformsh\Client\Model\Project;
use Platformsh\Client\Model\SshKey;
use Platformsh\Client\Model\Subscription;

class PlatformClient
{

    /** @var string[] */
    protected $availableClusters = ['eu_west', 'us_east'];

    /** @var ConnectorInterface */
    protected $connector;

    /** @var string */
    protected $accountsEndpoint;

    /** @var array */
    protected $accountInfo;

    /**
     * @param ConnectorInterface $connector
     */
    public function __construct(ConnectorInterface $connector = null)
    {
        $this->connector = $connector ?: new Connector();
        $this->accountsEndpoint = $connector->getAccountsEndpoint();;
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
            $url = $this->accountsEndpoint . 'me';
            $this->accountInfo = (array) $client->get($url)->json();
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
            $client = $this->connector->getClient();
            // @todo get the actual ID added to the API
            $projectId = basename($project['endpoint']);
            $projects[$projectId] = Project::wrap($project, $project['endpoint'], $client);
        }

        return $projects;
    }

    /**
     * Get a single project by its ID.
     *
     * @param string $id
     *
     * @return Project|false
     */
    public function getProject($id)
    {
        $projects = $this->getProjects();
        if (!isset($projects[$id])) {
            return false;
        }
        $project = $projects[$id];

        return $project;
    }

    /**
     * Get a single project at a known location.
     *
     * @param string $id       The project ID.
     * @param string $hostname The hostname of the Platform.sh cluster API,
     *                         e.g. 'eu.platform.sh' or 'us.platform.sh'.
     * @param bool   $https    Whether to use HTTPS (default: true).
     *
     * @return Project|false
     */
    public function getProjectDirect($id, $hostname, $https = true)
    {
        $scheme = $https ? 'https' : 'http';
        $endpoint = "$scheme://$hostname/api/projects/";
        return Project::get($id, '', $this->connector->getClient($endpoint));
    }

    /**
     * Get the logged-in user's SSH keys.
     *
     * @param bool $reset
     *
     * @return SshKey[]
     */
    public function getSshKeys($reset = false)
    {
        $data = $this->getAccountInfo($reset);

        return SshKey::wrapCollection($data['ssh_keys'], $this->accountsEndpoint, $this->connector->getClient());
    }

    /**
     * Get a single SSH key by its ID.
     *
     * @param string|int $id
     * @param bool       $reset
     *
     * @return SshKey|false
     */
    public function getSshKey($id, $reset = false)
    {
        $data = $this->getAccountInfo($reset);
        if (!isset($data['ssh_keys'][$id])) {
            return false;
        }

        return SshKey::wrap($data['ssh_keys'][$id], $this->accountsEndpoint, $this->connector->getClient());
    }

    /**
     * Add an SSH public key to the logged-in user's account.
     *
     * @param string $value The SSH key value.
     * @param string $title A title for the key (optional).
     *
     * @return SshKey
     */
    public function addSshKey($value, $title = null)
    {
        $values = ['value' => $value];
        if ($title) {
            $values['title'] = $title;
        }
        $url = $this->accountsEndpoint . 'ssh_keys';

        return SshKey::create($values, $url, $this->connector->getClient());
    }

    /**
     * Create a new Platform.sh subscription.
     *
     * @param string $cluster
     * @param string $plan
     * @param string $title
     *
     * @return Subscription
     */
    public function createSubscription($cluster = null, $plan = 'Development', $title = 'Untitled Project')
    {
        if ($cluster === null) {
            $cluster = reset($this->availableClusters);
        }
        $url = $this->accountsEndpoint . 'subscriptions';
        $values = ['cluster' => $cluster, 'plan' => $plan, 'title' => $title];
        return Subscription::create($values, $url, $this->connector->getClient());
    }

    /**
     * Get a list of your Platform.sh subscriptions.
     *
     * @return Subscription[]
     */
    public function getSubscriptions()
    {
        $url = $this->accountsEndpoint . 'subscriptions';
        return Subscription::getCollection($url, 0, [], $this->connector->getClient());
    }
}
