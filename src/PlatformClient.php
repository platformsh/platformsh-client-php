<?php

declare(strict_types=1);

namespace Platformsh\Client;

use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Utils as Psr7Utils;
use GuzzleHttp\Utils;
use Platformsh\Client\Connection\Connector;
use Platformsh\Client\Connection\ConnectorInterface;
use Platformsh\Client\Exception\ApiResponseException;
use Platformsh\Client\Exception\ProjectReferenceException;
use Platformsh\Client\Model\BasicProjectInfo;
use Platformsh\Client\Model\Billing\PlanRecord;
use Platformsh\Client\Model\Billing\PlanRecordQuery;
use Platformsh\Client\Model\Catalog;
use Platformsh\Client\Model\CentralizedPermissions\UserExtendedAccess;
use Platformsh\Client\Model\Filter\Filter;
use Platformsh\Client\Model\Organization\Organization;
use Platformsh\Client\Model\Plan;
use Platformsh\Client\Model\Project;
use Platformsh\Client\Model\ProjectStub;
use Platformsh\Client\Model\Region;
use Platformsh\Client\Model\Result;
use Platformsh\Client\Model\SetupOptions;
use Platformsh\Client\Model\SshKey;
use Platformsh\Client\Model\Subscription;
use Platformsh\Client\Model\Subscription\SubscriptionOptions;
use Platformsh\Client\Model\Team\Team;
use Platformsh\Client\Model\User;

class PlatformClient
{
    protected ConnectorInterface $connector;

    /**
     * @var array|null A per-client cache for account info
     */
    protected ?array $accountInfo = null;

    /**
     * @var string|false|null A per-client cache for the user ID
     */
    protected false|null|string $userId;

    public function __construct(ConnectorInterface $connector = null)
    {
        $this->connector = $connector ?: new Connector();
    }

    /**
     * @return ConnectorInterface
     */
    public function getConnector(): ConnectorInterface|Connector
    {
        return $this->connector;
    }

    /**
     * Get a single project by its ID.
     */
    public function getProject(string $id, string $hostname = null, bool $https = true): Project|false
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
     * @return Project[]
     *@deprecated replaced by getMyProjects()
     */
    public function getProjects(bool $reset = false): array
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
     * @return ProjectStub[]
     *@deprecated replaced by getMyProjects()
     */
    public function getProjectStubs(bool $reset = false): array
    {
        return ProjectStub::wrapCollection($this->getAccountInfo($reset), $this->apiUrl(), $this->connector->getClient());
    }

    /**
     * Returns all the projects that the current user can access.
     *
     * @return BasicProjectInfo[]
     *   A list of basic project information.
     */
    public function getMyProjects(string $vendor = null): array
    {
        $projects = [];
        if (! empty($this->connector->getConfig()['centralized_permissions_enabled'])) {
            $userId = $this->getMyUserId();
            if ($userId === false) {
                throw new \InvalidArgumentException('No user ID specified');
            }
            $strict = ! empty($this->connector->getConfig()['strict_project_references']);
            $extendedAccesses = UserExtendedAccess::byUser($userId, [
                'query' => [
                    'filter[resource_type]' => 'project',
                ],
            ], $this->connector->getClient());
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
     *@see PlatformClient::getUser()
     */
    public function getAccountInfo(bool $reset = false): ?array
    {
        if (! isset($this->accountInfo) || $reset) {
            $url = $this->apiUrl() . '/me';
            try {
                $this->accountInfo = $this->simpleGet($url);
            } catch (GuzzleException $e) {
                throw ApiResponseException::wrapGuzzleException($e);
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
     * @param bool $https    Whether to use HTTPS (default: true).
     *
     *@internal It's now better to use getProject(). This method will be made
     *           private in a future release.
     */
    public function getProjectDirect(string $id, string $hostname, bool $https = true): Project|false
    {
        $scheme = $https ? 'https' : 'http';
        $collection = "{$scheme}://{$hostname}/api/projects";
        $project = Project::get($id, $collection, $this->connector->getClient());
        if ($project && ($apiUrl = $this->connector->getApiUrl())) {
            $project->setApiUrl($apiUrl);
        }
        return $project;
    }

    /**
     * Get the logged-in user's SSH keys.
     *
     * @return SshKey[]
     */
    public function getSshKeys(bool $reset = false): array
    {
        $data = $this->getAccountInfo($reset);

        return SshKey::wrapCollection($data['ssh_keys'], $this->apiUrl() . '/ssh_keys', $this->connector->getClient());
    }

    /**
     * Get a single SSH key by its ID.
     */
    public function getSshKey(int|string $id): false|SshKey
    {
        $url = $this->apiUrl() . '/ssh_keys';

        return SshKey::get($id, $url, $this->connector->getClient());
    }

    /**
     * Add an SSH public key to the logged-in user's account.
     *
     * @param string $value The SSH key value.
     * @param string|null $title A title for the key (optional).
     */
    public function addSshKey(string $value, string $title = null): Result
    {
        $values = $this->cleanRequest([
            'value' => $value,
            'title' => $title,
        ]);
        $url = $this->apiUrl() . '/ssh_keys';

        return SshKey::create($values, $url, $this->connector->getClient());
    }

    /**
     * Create a new Platform.sh subscription.
     *
     * @param string|SubscriptionOptions $options
     *   Subscription request options, which override the other arguments.
     *   If a string is passed, it will be used as the region ID (deprecated). See getRegions().
     * @param string|null $plan                The plan. See getPlans(). @deprecated
     * @param string|null $title               The project title. @deprecated
     * @param int|null $storage             The storage of each environment, in MiB. @deprecated
     * @param int|null $environments        The number of available environments. @deprecated
     * @param array  $activation_callback An activation callback for the subscription. @deprecated
     * @param string|null $options_url         The catalog options URL. See getCatalog(). @deprecated
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
    public function createSubscription(SubscriptionOptions|string $options, string $plan = null, string $title = null, int $storage = null, int $environments = null, array $activation_callback = null, string $options_url = null): Subscription
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
     * @return Subscription[]
     */
    public function getSubscriptions(string $organizationId = null): array
    {
        if (isset($organizationId)) {
            $url = $this->apiUrl() . '/organizations/' . $organizationId . '/subscriptions';
        } else {
            $url = $this->apiUrl() . '/subscriptions';
        }
        return Subscription::getCollection($url, 0, [], $this->connector->getClient());
    }

    /**
     * Get a subscription by its ID.
     */
    public function getSubscription(int|string $id): Subscription|false
    {
        $url = $this->apiUrl() . '/subscriptions';
        return Subscription::get($id, $url, $this->connector->getClient());
    }

    /**
     * Estimate the cost of a subscription.
     *
     * @param string $plan         The plan machine name.
     * @param int $storage      The allowed storage per environment (MiB).
     * @param int $environments The number of environments.
     * @param int $users        The number of users.
     * @param string|null $countryCode  A two-letter country code.
     * @param string|null $organizationId An organization ID.
     *
     * @return array An array containing at least 'total' (a formatted price).
     */
    public function getSubscriptionEstimate(string $plan, int $storage, int $environments, int $users, string $countryCode = null, string $organizationId = null): array
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

        return $this->simpleGet($url, $options);
    }

    /**
     * Get a list of available plans.
     *
     * @return Plan[]
     */
    public function getPlans(): array
    {
        return Plan::getCollection($this->apiUrl() . '/plans', 0, [], $this->getConnector()->getClient());
    }

    /**
     * Get a list of available regions.
     *
     * @return Region[]
     */
    public function getRegions(): array
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
    public function getPlanRecords(PlanRecordQuery $query = null): array
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
            Psr7Utils::uriFor($this->connector->getConfig()['certifier_url'])->withPath('/ssh'),
            [
                'json' => [
                    'key' => $publicKey,
                ],
            ]
        );

        return Utils::jsonDecode((string) $response->getBody(), true)['certificate'];
    }

    /**
     * Get the project options catalog.
     *
     * @return \Platformsh\Client\Model\CatalogItem[]
     */
    public function getCatalog(): array
    {
        return Catalog::create([], $this->apiUrl() . '/setup/catalog', $this->getConnector()->getClient());
    }

    /**
     * Get the setup options file for a user.
     *
     * @param string|null $vendor             The query string containing the vendor machine name.
     * @param string|null $plan               The machine name of the plan which has been selected during the project setup process.
     * @param string|null $options_url        The URL of a project options file which has been selected as a setup template.
     * @param string|null $username           The name of the account for which the project is to be created.
     * @param string|null $organization       The name of the organization for which the project is to be created.
     */
    public function getSetupOptions(string $vendor = null, string $plan = null, string $options_url = null, string $username = null, string $organization = null): SetupOptions
    {
        $url = $this->apiUrl() . '/setup/options';
        $options = $this->cleanRequest([
            'vendor' => $vendor,
            'plan' => $plan,
            'options_url' => $options_url,
            'username' => $username,
            'organization' => $organization,
        ]);

        return SetupOptions::create($options, $url, $this->connector->getClient());
    }

    /**
     * Get a user account.
     *
     * @param string|null $id
     *   The user ID. Defaults to the current user.
     */
    public function getUser(string $id = null): false|User
    {
        if (! $this->connector->getApiUrl()) {
            throw new \RuntimeException('No API URL configured');
        }
        if ($id === null) {
            $id = 'me';
        }
        return User::get($id, $this->connector->getApiUrl() . '/users', $this->connector->getClient());
    }

    /**
     * Returns the current user's ID, if any.
     *
     * @param bool $reset Reset the per-client cache.
     *
     * @return string|false
     *   The user ID, or false if the access token is not associated with a user.
     */
    public function getMyUserId(bool $reset = false): string|false
    {
        if (isset($this->userId) && ! $reset) {
            return $this->userId;
        }

        $accessToken = $this->connector->getAccessToken();
        if ($accessToken && ($claims = $this->unsafeGetJwtClaims($accessToken))) {
            if (! empty($claims['sub']) && preg_match('/^[a-zA-Z0-9-]+$/', $claims['sub']) === 1) {
                return $this->userId = $claims['sub'];
            }
            return $this->userId = false;
        }

        try {
            return $this->userId = $this->getUser('me')->id;
        } catch (BadResponseException $e) {
            if ($e->getResponse() && $e->getResponse()->getStatusCode() === 403) {
                return $this->userId = false;
            }
            throw $e;
        }
    }

    /**
     * Lists all available organizations.
     *
     * @param \Platformsh\Client\Model\Filter\FilterInterface[] $filters
     *
     * @return Organization[]
     */
    public function listOrganizations(array $filters = []): array
    {
        if (! $this->connector->getApiUrl()) {
            throw new \RuntimeException('No API URL configured');
        }
        $path = '/organizations';
        $options = [];
        if (! empty($filters)) {
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
     * @return Organization[]
     */
    public function listOrganizationsWithMember(string $userId): array
    {
        if (! $this->connector->getApiUrl()) {
            throw new \RuntimeException('No API URL configured');
        }
        $path = '/users/' . \rawurlencode($userId) . '/organizations';
        return Organization::getCollection($path, 0, [], $this->connector->getClient());
    }

    /**
     * Lists organizations owned by the given user ID.
     *
     * @return Organization[]
     */
    public function listOrganizationsByOwner(string $ownerId): array
    {
        if (! $this->connector->getApiUrl()) {
            throw new \RuntimeException('No API URL configured');
        }
        return $this->listOrganizations([new Filter('owner_id', $ownerId)]);
    }

    /**
     * Gets a single organization by name.
     */
    public function getOrganizationByName(string $name): Organization|false
    {
        return $this->getOrganizationById('name=' . $name);
    }

    /**
     * Gets a single organization.
     */
    public function getOrganizationById(string $id): Organization|false
    {
        if (! $this->connector->getApiUrl()) {
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
     * @param string $country An ISO 2-letter country code.
     * @param string $owner The organization owner ID. Leave empty to use the current user.
     */
    public function createOrganization(string $name, string $label = '', string $country = '', string $owner = ''): Organization
    {
        if (! $this->connector->getApiUrl()) {
            throw new \RuntimeException('No API URL configured');
        }
        $url = '/organizations';
        $values = [
            'name' => $name,
            'label' => $label,
            'country' => $country,
        ];
        if ($owner !== '') {
            $values['owner_id'] = $owner;
        }
        return Organization::create($values, $url, $this->connector->getClient());
    }

    /**
     * Fetches a team by ID.
     *
     *@throws \RuntimeException if the given organization and team IDs conflict
     */
    public function getTeam(string $id, Organization $organization = null): false|Team
    {
        if (! $this->connector->getApiUrl()) {
            throw new \RuntimeException('No API URL configured');
        }
        $team = Team::get($id, '/teams', $this->connector->getClient());
        if ($organization && $team && $team->organization_id !== $organization->id) {
            throw new \RuntimeException(sprintf('Found team %s, but it is not part of the specified organization %s', $team->id, $organization->id));
        }
        return $team;
    }

    /**
     * Locate a project by ID.
     *
     * @param string $id
     *   The project ID.
     *
     * @return string|false
     *   The project's API endpoint.
     */
    protected function locateProject(string $id): false|string
    {
        $url = rtrim($this->connector->getAccountsEndpoint(), '/') . '/projects/' . rawurlencode($id);
        try {
            $result = $this->simpleGet($url);
        } catch (BadResponseException $e) {
            $ignoredErrorCodes = [403, 404];
            if (in_array($e->getResponse()->getStatusCode(), $ignoredErrorCodes, true)) {
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
     * Filter a request array to remove null values.
     */
    protected function cleanRequest(array $request): array
    {
        return array_filter($request, function ($element) {
            return $element !== null;
        });
    }

    /**
     * Returns the base URL of the API, without trailing slash.
     */
    private function apiUrl(): string
    {
        return $this->connector->getApiUrl() ?: rtrim($this->connector->getAccountsEndpoint(), '/');
    }

    /**
     * Get a URL and return the JSON-decoded response.
     *
     * @throws GuzzleException
     */
    private function simpleGet(string $url, array $options = []): array
    {
        return (array) Utils::jsonDecode(
            $this->getConnector()
                ->getClient()
                ->request('get', $url, $options)
                ->getBody()
                ->getContents(),
            true
        );
    }

    /**
     * Returns the payload of a JWT without verification.
     */
    private function unsafeGetJwtClaims(string $jwt): false|array
    {
        $split = explode('.', $jwt, 3);
        if (! isset($split[1])) {
            return false;
        }
        $json = base64_decode($split[1], true);
        if (! $json) {
            return false;
        }
        return json_decode($json, true) ?: false;
    }
}
