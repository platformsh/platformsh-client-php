<?php

namespace Platformsh\Client;

use Platformsh\Client\Connection\Connector;
use Platformsh\Client\Connection\ConnectorInterface;
use Platformsh\Client\Model\Project;
use Platformsh\Client\Model\SshKey;
use Platformsh\Client\Model\Subscription;

class PlatformClient
{

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
        $this->accountsEndpoint = $this->connector->getAccountsEndpoint();;
    }

    /**
     * @return ConnectorInterface
     */
    public function getConnector()
    {
        return $this->connector;
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
        $collection = "$scheme://$hostname/api/projects";
        return Project::get($id, $collection, $this->connector->getClient());
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
        $values = $this->cleanRequest(['value' => $value, 'title' => $title]);
        $url = $this->accountsEndpoint . 'ssh_keys';

        return SshKey::create($values, $url, $this->connector->getClient());
    }

    /**
     * Filter a request array to remove null values.
     *
     * @param array $request
     *
     * @return array
     */
    protected function cleanRequest(array $request)
    {
        return array_filter($request, function ($element) {
            return $element !== null;
        });
    }

    /**
     * Create a new Platform.sh subscription.
     *
     * @param string $cluster The cluster. See Subscription::$availableClusters.
     * @param string $plan    The plan. See Subscription::$availablePlans.
     * @param string $title   The project title.
     * @param int    $storage The storage of each environment, in MiB.
     * @param int    $environments The number of available environments.
     *
     * @return Subscription
     */
    public function createSubscription($cluster, $plan = 'development', $title = null, $storage = null, $environments = null)
    {
        $url = $this->accountsEndpoint . 'subscriptions';
        $values = $this->cleanRequest([
          'project_cluster' => $cluster,
          'plan' => $plan,
          'project_title' => $title,
          'storage' => $storage,
          'environments' => $environments,
        ]);

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

    /**
     * Get a subscription by its ID.
     *
     * @param string|int $id
     *
     * @return Subscription|false
     */
    public function getSubscription($id)
    {
        $url = $this->accountsEndpoint . 'subscriptions';
        return Subscription::get($id, $url, $this->connector->getClient());
    }
}
