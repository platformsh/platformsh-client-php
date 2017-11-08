<?php

namespace Platformsh\Client;

use GuzzleHttp\Exception\BadResponseException;
use Platformsh\Client\Connection\Connector;
use Platformsh\Client\Connection\ConnectorInterface;
use Platformsh\Client\Exception\ApiResponseException;
use Platformsh\Client\Model\Project;
use Platformsh\Client\Model\Result;
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
        $this->accountsEndpoint = $this->connector->getAccountsEndpoint();
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
     * @param string $hostname
     * @param bool   $https
     *
     * @return Project|false
     */
    public function getProject($id, $hostname = null, $https = true)
    {
        // Search for a project in the user's project list.
        foreach ($this->getProjects() as $project) {
            if ($project->id === $id) {
                return $project;
            }
        }

        // Look for a project directly if the hostname is known.
        if ($hostname !== null) {
            return $this->getProjectDirect($id, $hostname, $https);
        }

        // Use the project locator.
        if ($url = $this->locateProject($id)) {
            return Project::get($url, null, $this->connector->getClient());
        }

        return false;
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
        $client = $this->connector->getClient();
        $projects = [];
        foreach ($data['projects'] as $project) {
            // Each project has its own endpoint on a Platform.sh region.
            $projects[] = new Project($project, $project['endpoint'], $client);
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
            $url = $this->accountsEndpoint . 'me';
            try {
                $this->accountInfo = $this->simpleGet($url);
            }
            catch (BadResponseException $e) {
                throw ApiResponseException::create($e->getRequest(), $e->getResponse(), $e->getPrevious());
            }
        }

        return $this->accountInfo;
    }

    /**
     * Get a URL and return the JSON-decoded response.
     *
     * @param string $url
     * @param array  $options
     *
     * @return array
     */
    private function simpleGet($url, array $options = [])
    {
        return (array) \GuzzleHttp\json_decode(
          $this->getConnector()
               ->getClient()
               ->request('get', $url, $options)
               ->getBody()
               ->getContents(),
          true
        );
    }

    /**
     * Get a single project at a known location.
     *
     * @param string $id       The project ID.
     * @param string $hostname The hostname of the Platform.sh regional API,
     *                         e.g. 'eu.platform.sh' or 'us.platform.sh'.
     * @param bool   $https    Whether to use HTTPS (default: true).
     *
     * @internal It's now better to use getProject(). This method will be made
     *           private in a future release.
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
     * Locate a project by ID.
     *
     * @param string $id
     *   The project ID.
     *
     * @return string
     *   The project's API endpoint.
     */
    protected function locateProject($id)
    {
        $url = $this->accountsEndpoint . 'projects/' . rawurlencode($id);
        try {
            $result = $this->simpleGet($url);
        }
        catch (BadResponseException $e) {
            $response = $e->getResponse();
            // @todo Remove 400 from this array when the API is more liberal in validating project IDs.
            $ignoredErrorCodes = [400, 403, 404];
            if ($response && in_array($response->getStatusCode(), $ignoredErrorCodes)) {
                return false;
            }
            throw ApiResponseException::create($e->getRequest(), $e->getResponse(), $e->getPrevious());
        }

        return isset($result['endpoint']) ? $result['endpoint'] : false;
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
     *
     * @return SshKey|false
     */
    public function getSshKey($id)
    {
        $url = $this->accountsEndpoint . 'ssh_keys';

        return SshKey::get($id, $url, $this->connector->getClient());
    }

    /**
     * Add an SSH public key to the logged-in user's account.
     *
     * @param string $value The SSH key value.
     * @param string $title A title for the key (optional).
     *
     * @return Result
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
     * @param string $region  The region. See Subscription::$availableRegions.
     * @param string $plan    The plan. See Subscription::$availablePlans.
     * @param string $title   The project title.
     * @param int    $storage The storage of each environment, in MiB.
     * @param int    $environments The number of available environments.
     * @param array  $activationCallback An activation callback for the subscription.
     *
     * @return Subscription
     */
    public function createSubscription($region, $plan = 'development', $title = null, $storage = null, $environments = null, array $activationCallback = null)
    {
        $url = $this->accountsEndpoint . 'subscriptions';
        $values = $this->cleanRequest([
          'project_region' => $region,
          'plan' => $plan,
          'project_title' => $title,
          'storage' => $storage,
          'environments' => $environments,
          'activation_callback' => $activationCallback,
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

    /**
     * Estimate the cost of a subscription.
     *
     * @param string $plan         The plan (see Subscription::$availablePlans).
     * @param int    $storage      The allowed storage per environment (in GiB).
     * @param int    $environments The number of environments.
     * @param int    $users        The number of users.
     *
     * @return array An array containing at least 'total' (a formatted price).
     */
    public function getSubscriptionEstimate($plan, $storage, $environments, $users)
    {
        $options = [];
        $options['query'] = [
            'plan' => $plan,
            'storage' => $storage,
            'environments' => $environments,
            'user_licenses' => $users,
        ];
        try {
            return $this->simpleGet($this->accountsEndpoint . 'estimate', $options);
        } catch (BadResponseException $e) {
            throw ApiResponseException::create($e->getRequest(), $e->getResponse(), $e->getPrevious());
        }
    }
}
