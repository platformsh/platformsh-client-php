<?php
/** @noinspection PhpDocMissingThrowsInspection */
/** @noinspection PhpUnhandledExceptionInspection */
declare(strict_types=1);

namespace Platformsh\Client;

use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Utils;
use Platformsh\Client\Connection\Connector;
use Platformsh\Client\Connection\ConnectorInterface;
use Platformsh\Client\Exception\ApiResponseException;
use Platformsh\Client\Model\Billing\PlanRecord;
use Platformsh\Client\Model\Billing\PlanRecordQuery;
use Platformsh\Client\Model\Organization;
use Platformsh\Client\Model\Plan;
use Platformsh\Client\Model\Project;
use Platformsh\Client\Model\Region;
use Platformsh\Client\Model\Catalog;
use Platformsh\Client\Model\Result;
use Platformsh\Client\Model\SetupOptions;
use Platformsh\Client\Model\SshKey;
use Platformsh\Client\Model\Subscription;
use Platformsh\Client\Model\Subscription\SubscriptionOptions;
use Platformsh\Client\Model\User;

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
     * Returns the base URL of the API, without trailing slash.
     */
    private function apiUrl()
    {
        return $this->connector->getApiUrl() ?: rtrim($this->connector->getAccountsEndpoint(), '/');
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

        // Use the API gateway.
        $apiUrl = $this->connector->getApiUrl();
        if ($apiUrl) {
            $project = Project::get($id, $apiUrl . '/projects', $this->connector->getClient());
            if ($project) {
                $project->setApiUrl($apiUrl);
            }
            return $project;
        }

        // Use the project locator.
        if ($url = $this->locateProject($id)) {
            $project = Project::get($url, null, $this->connector->getClient());
            if ($project && ($apiUrl = $this->connector->getApiUrl())) {
                $project->setApiUrl($apiUrl);
            }
            return $project;
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
        $apiUrl = $this->connector->getApiUrl();
        $projects = [];
        foreach ($data['projects'] as $data) {
            // Each project has its own endpoint on a Platform.sh region.
            $project = new Project($data, $data['endpoint'], $client);
            if ($apiUrl) {
                $project->setApiUrl($apiUrl);
            }
            $projects[] = $project;
        }

        return $projects;
    }

    /**
     * Get account information for the logged-in user.
     *
     * This information includes various integrated details such as the
     * projects the user can access, their registered SSH keys, and legacy
     * information.
     *
     * For purely user profile related information, getUser() is recommended.
     *
     * @see PlatformClient::getUser()
     *
     * @param bool $reset
     *
     * @return array
     */
    public function getAccountInfo($reset = false)
    {
        if (!isset($this->accountInfo) || $reset) {
            $url = $this->apiUrl() . '/me';
            try {
                $this->accountInfo = $this->simpleGet($url);
            }
            catch (GuzzleException $e) {
                throw ApiResponseException::wrapGuzzleException($e);
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
     * @throws \GuzzleHttp\Exception\GuzzleException
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
        $project = Project::get($id, $collection, $this->connector->getClient());
        if ($project && ($apiUrl = $this->connector->getApiUrl())) {
            $project->setApiUrl($apiUrl);
        }
        return $project;
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
        $url = rtrim($this->connector->getAccountsEndpoint(), '/') . '/projects/' . rawurlencode($id);
        try {
            $result = $this->simpleGet($url);
        }
        catch (BadResponseException $e) {
            $response = $e->getResponse();
            $ignoredErrorCodes = [403, 404];
            if ($response && in_array($response->getStatusCode(), $ignoredErrorCodes)) {
                return false;
            }
            throw ApiResponseException::wrapGuzzleException($e);
        }

        if (isset($result['endpoint'])) {
            return $result['endpoint'];
        }
        if (isset($result['_links']['self']['href'])) {
            return $result['_links']['self']['href'];
        }
        return false;
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

        return SshKey::wrapCollection($data['ssh_keys'], $this->apiUrl() . '/ssh_keys', $this->connector->getClient());
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
        $url = $this->apiUrl() . '/ssh_keys';

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
        $url = $this->apiUrl() . '/ssh_keys';

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
     * Get a list of your Platform.sh organizations.
     *
     * @return Organization[]
     */
    public function getOrganizations() {
        $url = $this->apiUrl() . '/organizations';
        return Organization::getCollection($url, 0, [], $this->connector->getClient());
    }

    /**
     * Get an organization by its ID.
     *
     * @param string $id
     *
     * @return Organization|false
     */
    public function getOrganization($id)
    {
        $url = $this->apiUrl() . '/organizations';
        return Organization::get($id, $url, $this->connector->getClient());
    }

    /**
     * Create a new Platform.sh subscription.
     *
     * @param SubscriptionOptions|string $options
     *   Subscription request options, which override the other arguments.
     *   If a string is passed, it will be used as the region ID (deprecated). See getRegions().
     * @param string $plan                The plan. See getPlans(). @deprecated
     * @param string $title               The project title. @deprecated
     * @param int    $storage             The storage of each environment, in MiB. @deprecated
     * @param int    $environments        The number of available environments. @deprecated
     * @param array  $activation_callback An activation callback for the subscription. @deprecated
     * @param string $options_url         The catalog options URL. See getCatalog(). @deprecated
     *
     * @return Subscription
     *   A subscription, representing a project. Use Subscription::wait() or
     *   similar code to wait for the subscription's project to be provisioned
     *   and activated.
     *
     * @see PlatformClient::getCatalog()
     * @see PlatformClient::getRegions()
     * @see Subscription::wait()
     *
     * @noinspection PhpTooManyParametersInspection
     */
    public function createSubscription($options, $plan = null, $title = null, $storage = null, $environments = null, array $activation_callback = null, $options_url = null)
    {
        $url = $this->apiUrl() . '/subscriptions';
        if ($options instanceof SubscriptionOptions) {
            $values = $options->toArray();
        } elseif (\is_string($options)) {
            \trigger_error('The previous arguments list has been replaced by a single SubscriptionOptions argument', E_USER_DEPRECATED);
            if ($plan === null) {
                // Backwards-compatible default.
                $plan = 'development';
            }
            $values = $this->cleanRequest([
                'project_region' => $options,
                'plan' => $plan,
                'project_title' => $title,
                'storage' => $storage,
                'environments' => $environments,
                'activation_callback' => $activation_callback,
                'options_url' => $options_url,
            ]);
        } else {
            throw new \InvalidArgumentException('The first argument must be a SubscriptionOptions object or a string');
        }

        return Subscription::create($values, $url, $this->connector->getClient());
    }

    /**
     * Get a list of your Platform.sh subscriptions.
     *
     * @return Subscription[]
     */
    public function getSubscriptions()
    {
        $url = $this->apiUrl() . '/subscriptions';
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
        $url = $this->apiUrl() . '/subscriptions';
        return Subscription::get($id, $url, $this->connector->getClient());
    }

    /**
     * Estimate the cost of a subscription.
     *
     * @param string      $plan         The plan machine name.
     * @param int         $storage      The allowed storage per environment
     *                                  (MiB).
     * @param int         $environments The number of environments.
     * @param int         $users        The number of users.
     * @param string|null $countryCode  A two-letter country code.
     *
     * @return array An array containing at least 'total' (a formatted price).
     */
    public function getSubscriptionEstimate($plan, $storage, $environments, $users, $countryCode = null)
    {
        $options = [];
        $options['query'] = [
            'plan' => $plan,
            'storage' => $storage,
            'environments' => $environments,
            'user_licenses' => $users,
        ];
        if ($countryCode !== null) {
            $options['query']['country_code'] = $countryCode;
        }

        return $this->simpleGet($this->apiUrl() . '/subscriptions/estimate', $options);
    }

    /**
     * Get a list of available plans.
     *
     * @return Plan[]
     */
    public function getPlans()
    {
        return Plan::getCollection($this->apiUrl() . '/plans', 0, [], $this->getConnector()->getClient());
    }

    /**
     * Get a list of available regions.
     *
     * @return Region[]
     */
    public function getRegions()
    {
        return Region::getCollection($this->apiUrl() . '/regions', 0, [], $this->getConnector()->getClient());
    }

    /**
     * Get plan records.
     *
     * @param PlanRecordQuery|null $query A query to restrict the returned plans.
     *
     * @return PlanRecord[]
     */
    public function getPlanRecords(PlanRecordQuery $query = null)
    {
        $url = $this->apiUrl() . '/records/plan';
        $options = [];

        if ($query) {
            $options['query'] = $query->getParams();
        }

        return PlanRecord::getCollection($url, 0, $options, $this->connector->getClient());
    }

    /**
     * Request an SSH certificate.
     *
     * @param string $publicKey
     *   The contents of an SSH public key. Do not reuse a key that had other
     *   purposes: generate a dedicated key pair for the current user.
     *
     * @return string
     *   An SSH certificate, which should be saved alongside the SSH key pair,
     *   e.g. as "id_rsa-cert.pub", alongside "id_rsa" and "id_rsa.pub".
     */
    public function getSshCertificate(string $publicKey): string
    {
        $response = $this->connector->getClient()->post(
            Utils::uriFor($this->connector->getConfig()['certifier_url'])->withPath('/ssh'),
            ['json' => ['key' => $publicKey]]
        );

        return \GuzzleHttp\json_decode($response->getBody(), true)['certificate'];
    }

    /**
     * Get the project options catalog.
     *
     * @return \Platformsh\Client\Model\CatalogItem[]
     */
    public function getCatalog()
    {
        return Catalog::create([], $this->apiUrl() . '/setup/catalog', $this->getConnector()->getClient());
    }

    /**
     * Get the setup options file for a user.
     *
     * @param string $vendor             The query string containing the vendor machine name.
     * @param string $plan               The machine name of the plan which has been selected during the project setup process.
     * @param string $options_url        The URL of a project options file which has been selected as a setup template.
     * @param string $username           The name of the account for which the project is to be created.
     * @param string $organization       The name of the organization for which the project is to be created.
     *
     * @return SetupOptions
     */
    public function getSetupOptions($vendor = NULL, $plan = NULL, $options_url = NULL, $username = NULL, $organization = NULL)
    {
        $url = $this->apiUrl() . '/setup/options';
        $options = $this->cleanRequest([
          'vendor' => $vendor,
          'plan' => $plan,
          'options_url' => $options_url,
          'username' => $username,
          'organization' => $organization
        ]);

        return SetupOptions::create($options, $url, $this->connector->getClient());
    }

    /**
     * Get a user account.
     *
     * @param string|null $id
     *   The user ID. Defaults to the current user.
     *
     * @return User|false
     */
    public function getUser($id = null)
    {
        if (!$this->connector->getApiUrl()) {
            throw new \RuntimeException('No API URL configured');
        }
        if ($id === null) {
            $id = 'me';
        }
        return User::get($id, $this->connector->getApiUrl() . '/users', $this->connector->getClient());
    }
}
