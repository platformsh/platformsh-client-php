<?php

namespace Platformsh\Client;

use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Url;
use Platformsh\Client\Connection\Connector;
use Platformsh\Client\Connection\ConnectorInterface;
use Platformsh\Client\Exception\ApiResponseException;
use Platformsh\Client\Exception\ProjectReferenceException;
use Platformsh\Client\Model\BasicProjectInfo;
use Platformsh\Client\Model\CentralizedPermissions\UserExtendedAccess;
use Platformsh\Client\Model\Filter\Filter;
use Platformsh\Client\Model\Organization\Organization;
use Platformsh\Client\Model\Plan;
use Platformsh\Client\Model\Project;
use Platformsh\Client\Model\ProjectStub;
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

    /** @var array|null A per-client cache for account info */
    protected $accountInfo;

    /** @var string|null A per-client cache for the user ID */
    protected $userId;

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
     * @deprecated replaced by getMyProjects()
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
     * Returns the logged-in user's project stubs.
     *
     * @deprecated replaced by getMyProjects()
     *
     * @param bool $reset
     *
     * @return ProjectStub[]
     */
    public function getProjectStubs($reset = false)
    {
        return ProjectStub::wrapCollection($this->getAccountInfo($reset), $this->apiUrl(), $this->connector->getClient());
    }

    /**
     * Returns all the projects that the current user can access.
     *
     * @param string|null $vendor
     *
     * @return BasicProjectInfo[]
     *   A list of basic project information.
     */
    public function getMyProjects($vendor = null)
    {
        $projects = [];
        if (!empty($this->connector->getConfig()['centralized_permissions_enabled'])) {
            $userId = $this->getMyUserId();
            if ($userId === false) {
                throw new \InvalidArgumentException('No user ID specified');
            }
            $strict = !empty($this->connector->getConfig()['strict_project_references']);
            $extendedAccesses = UserExtendedAccess::byUser($userId, ['query' => ['filter[resource_type]' => 'project']], $this->connector->getClient());
            foreach ($extendedAccesses as $extendedAccess) {
                try {
                    $project = BasicProjectInfo::fromExtendedAccess($extendedAccess);
                    if ($vendor === null || $vendor === $project->vendor) {
                        $projects[] = $project;
                    }
                } catch (ProjectReferenceException $e) {
                    // This exception may be thrown on non-production
                    // environments where grants and project reference
                    // information are not correctly synchronized.
                    if ($strict) {
                        throw $e;
                    }
                    trigger_error($e->getMessage(), E_USER_WARNING);
                }
            }
        } else {
            foreach ($this->getProjectStubs() as $stub) {
                $projects[] = BasicProjectInfo::fromStub($stub);
            }
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
     * For projects, getMyProjects() is recommended.
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
            $client = $this->connector->getClient();
            $url = $this->apiUrl() . '/me';
            try {
                $this->accountInfo = (array) $client->get($url)->json();
            }
            catch (BadResponseException $e) {
                throw ApiResponseException::create($e->getRequest(), $e->getResponse(), $e->getPrevious());
            }
        }

        return $this->accountInfo;
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
        $client = $this->connector->getClient();
        $url = rtrim($this->connector->getAccountsEndpoint(), '/') . '/projects/' . rawurlencode($id);
        try {
            $result = (array) $client->get($url)->json();
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

        if ($id = $options->organizationId()) {
            $url = $this->apiUrl() . '/organizations/' . \rawurlencode($id) . '/subscriptions';
        } else {
            $url = $this->apiUrl() . '/subscriptions';
        }

        return Subscription::create($values, $url, $this->connector->getClient());
    }

    /**
     * Get a list of your Platform.sh subscriptions.
     *
     * @param string|null $organizationId
     *
     * @return Subscription[]
     */
    public function getSubscriptions($organizationId = null)
    {
        if (isset($organizationId)) {
            $url = $this->apiUrl() . '/' . $organizationId . '/subscriptions';
        } else {
            $url = $this->apiUrl() . '/subscriptions';
        }
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
     * @param int         $storage      The allowed storage per environment (MiB).
     * @param int         $environments The number of environments.
     * @param int         $users        The number of users.
     * @param string|null $countryCode  A two-letter country code.
     * @param string|null $organizationId An organization ID.
     *
     * @return array An array containing at least 'total' (a formatted price).
     */
    public function getSubscriptionEstimate($plan, $storage, $environments, $users, $countryCode = null, $organizationId = null)
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

        if ($organizationId) {
            $url = $this->apiUrl() . '/organizations/' . \rawurlencode($organizationId) . '/subscriptions/estimate';
        } else {
            $url = $this->apiUrl() . '/subscriptions/estimate';
        }

        try {
            $response = $this->connector
                ->getClient()
                ->get($url, $options);
        } catch (BadResponseException $e) {
            throw ApiResponseException::create($e->getRequest(), $e->getResponse(), $e->getPrevious());
        }

        return $response->json();
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
    public function getSshCertificate($publicKey)
    {
        $url = Url::fromString($this->connector->getConfig()['certifier_url'])
            ->combine('/ssh')
            ->__toString();
        $response = $this->connector->getClient()->post(
            $url,
            ['json' => ['key' => $publicKey]]
        );

        return $response->json()['certificate'];
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
     *   The user ID. Defaults to the current user's ID.
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
        return User::get($id, '/users', $this->connector->getClient());
    }

    /**
     * Returns the current user's ID, if any.
     *
     * @param bool $reset Reset the per-client cache.
     *
     * @return string|false
     *   The user ID, or false if the access token is not associated with a user.
     */
    public function getMyUserId($reset = false)
    {
        if (isset($this->userId) && !$reset) {
            return $this->userId;
        }

        $accessToken = $this->connector->getAccessToken();
        if ($accessToken && ($claims = $this->unsafeGetJwtClaims($accessToken))) {
            if (!empty($claims['sub']) && preg_match('/^[a-zA-Z0-9-]+$/', $claims['sub']) === 1) {
                return $this->userId = $claims['sub'];
            }
            return $this->userId = false;
        }

        try {
            // Use the Auth API (/users/me) if enabled.
            if (!empty($this->connector->getConfig()['auth_api_enabled'])) {
                return $this->userId = $this->getUser('me')->id;
            }
            // Otherwise fall back to the legacy account info function.
            return $this->userId = $this->getAccountInfo($reset)['id'];
        } catch (BadResponseException $e) {
            if ($e->getResponse() && $e->getResponse()->getStatusCode() === 403) {
                return $this->userId = false;
            }
            throw $e;
        }
    }

    /**
     * Returns the payload of a JWT without verification.
     *
     * @param string $jwt
     * @return array|false
     */
    private function unsafeGetJwtClaims($jwt)
    {
        $split = explode('.', $jwt, 3);
        if (!isset($split[1])) {
            return false;
        }
        $json = base64_decode($split[1], true);
        if (!$json) {
            return false;
        }
        return json_decode($json, true) ?: false;
    }

    /**
     * Lists all available organizations.
     *
     * @param \Platformsh\Client\Model\Filter\FilterInterface[] $filters
     *
     * @return Organization[]
     */
    public function listOrganizations(array $filters = [])
    {
        if (!$this->connector->getApiUrl()) {
            throw new \RuntimeException('No API URL configured');
        }
        $path = '/organizations';
        $options = [];
        if (!empty($filters)) {
            $options['query'] = [];
            foreach ($filters as $filter) {
                $options['query'] += $filter->params();
            }
        }
        return Organization::getCollection($this->connector->getApiUrl() . $path, 0, $options, $this->connector->getClient());
    }

    /**
     * Lists organizations of which the given user is a member.
     *
     * @param string $userId
     *
     * @return Organization[]
     */
    public function listOrganizationsWithMember($userId)
    {
        if (!$this->connector->getApiUrl()) {
            throw new \RuntimeException('No API URL configured');
        }
        $path = '/users/' . \rawurlencode($userId) . '/organizations';
        return Organization::getCollection($path, 0, [], $this->connector->getClient());
    }

    /**
     * Lists organizations owned by the given user ID.
     *
     * @param string $ownerId
     *
     * @return Organization[]
     */
    public function listOrganizationsByOwner($ownerId)
    {
        if (!$this->connector->getApiUrl()) {
            throw new \RuntimeException('No API URL configured');
        }
        return $this->listOrganizations([new Filter('owner_id', $ownerId)]);
    }

    /**
     * Gets a single organization by name.
     *
     * @param string $name
     *
     * @return Organization|false
     */
    public function getOrganizationByName($name)
    {
        return $this->getOrganizationById('name=' . $name);
    }

    /**
     * Gets a single organization.
     *
     * @param string $id
     *
     * @return Organization|false
     */
    public function getOrganizationById($id)
    {
        if (!$this->connector->getApiUrl()) {
            throw new \RuntimeException('No API URL configured');
        }
        return Organization::get($id, '/organizations', $this->connector->getClient());
    }

    /**
     * Creates a new organization.
     *
     * Warning: owning more than 1 organization will cause certain deprecated
     * APIs to stop working. The /subscriptions API now must be accessed under
     * /organizations/{id}/subscriptions, and the same applies to similar APIs
     * that are concerned with subscriptions or billing. The old API path will
     * only continue to work for users who own just 1 organization (or 0).
     *
     * @param string $name
     * @param string $label
     * @param string $country An ISO 2-letter country code.
     * @param string $owner The organization owner ID. Leave empty to use the current user.
     *
     * @return Organization
     */
    public function createOrganization($name, $label = '', $country = '', $owner = '')
    {
        if (!$this->connector->getApiUrl()) {
            throw new \RuntimeException('No API URL configured');
        }
        $url = '/organizations';
        $values = ['name' => $name, 'label' => $label, 'country' => $country];
        if ($owner !== '') {
            $values['owner_id'] = $owner;
        }
        return Organization::create($values, $url, $this->connector->getClient());
    }
}
