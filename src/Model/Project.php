<?php

namespace Platformsh\Client\Model;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Url;
use Platformsh\Client\Model\Activities\HasActivitiesInterface;
use Platformsh\Client\Model\Activities\HasActivitiesTrait;
use Platformsh\Client\Model\Invitation\AlreadyInvitedException;
use Platformsh\Client\Model\Invitation\ProjectInvitation;
use Platformsh\Client\Model\Invitation\Environment as InvitationEnvironment;
use Platformsh\Client\Model\Invitation\Permission as InvitationPermission;
use Platformsh\Client\Model\Project\Capabilities;
use Platformsh\Client\Model\Project\Settings;

/**
 * A Platform.sh project.
 *
 * @property-read string $id
 * @property-read string $title
 * @property-read string $created_at
 * @property-read string $updated_at
 * @property-read string $owner
 * @property-read string $default_branch
 * @property-read string $region
 */
class Project extends Resource implements HasActivitiesInterface
{
    use HasActivitiesTrait;

    private $urlViaGateway;

    /**
     * {@inheritDoc}
     *
     * Project information can come from a number of different places, which
     * do not always contain full information about each project. So this
     * overrides the Resource constructor to default $full to false.
     */
    public function __construct(array $data, $baseUrl = null, ClientInterface $client = null, $full = false)
    {
        parent::__construct($data, $baseUrl, $client, $full);
    }

    /**
     * Prevent deletion.
     *
     * @internal
     */
    public function delete()
    {
        throw new \BadMethodCallException("Projects should not be deleted directly. Delete the subscription instead.");
    }

    /**
     * Get the subscription ID for the project.
     *
     * @todo when APIs are unified, this can be a property
     *
     * @return string|int
     *   The ID is a numeric string. Legacy APIs may return an integer.
     */
    public function getSubscriptionId()
    {
        if (isset($this->data['subscription_id'])) {
            return $this->data['subscription_id'];
        }
        if (isset($this->data['subscription']['license_uri'])) {
            return basename($this->data['subscription']['license_uri']);
        }

        throw new \RuntimeException('Subscription ID not found');
    }

    /**
     * Get the Git URL for the project.
     *
     * @return string
     */
    public function getGitUrl()
    {
        $repository = $this->getProperty('repository');

        return $repository['url'];
    }

    /**
     * Get the users associated with a project.
     *
     * @return ProjectAccess[]
     */
    public function getUsers()
    {
        return ProjectAccess::getCollection($this->getLink('access'), 0, [], $this->client);
    }

    /**
     * Add a new user to a project.
     *
     * @param string $user   The user's UUID or email address (see $byUuid).
     * @param string $role   One of ProjectAccess::$roles.
     * @param bool   $byUuid Set true if $user is a UUID, or false (default) if
     *                       $user is an email address.
     *
     * @deprecated Users should now be invited via Project::inviteUserByEmail()
     *
     * @see Project::inviteUserByEmail()
     *
     * @return Result
     */
    public function addUser($user, $role, $byUuid = false)
    {
        trigger_error('Calling Project::addUser() is deprecated; the method will be removed in a future version. Use Project::inviteUserByEmail() instead.', E_USER_DEPRECATED);

        $property = $byUuid ? 'user' : 'email';
        $body = [$property => $user, 'role' => $role];

        return ProjectAccess::create($body, $this->getLink('access'), $this->client);
    }

    /**
     * Set the API gateway URL, e.g. 'https://api.platform.sh'.
     *
     * @param string $url
     */
    public function setApiUrl($url)
    {
        $projectUrl = Url::fromString($url)->combine('/projects/' . \urlencode($this->id))->__toString();
        $this->baseUrl = $this->urlViaGateway = $projectUrl;
    }

    /**
     * Invite a new user to a project using their email address.
     *
     * This is only possible after setting the API gateway URL. This will be
     * the case already if the project was instantiated via a PlatformClient
     * method such as PlatformClient::getProject(). Otherwise, use
     * Project::setApiUrl() before calling this method.
     *
     * @see Project::setApiUrl()
     * @see \Platformsh\Client\PlatformClient::getProject()
     *
     * Normally either a list of $environments should be given, or the project-level $role should be 'admin'.
     *
     * @param string $email
     *   The user's email address.
     * @param string $role
     *   The user's role on the project ('viewer' or 'admin').
     * @param InvitationEnvironment[] $environments
     *   Deprecated. A list of environments for the invitation. Replaced by $permissions.
     * @param bool $force
     *   Whether to re-send the invitation, if an invitation has already been sent to the same email address.
     * @param InvitationPermission[] $permissions
     *   A list of permissions for the invitation. Only used if the project role is not 'admin'.
     *
     * @throws AlreadyInvitedException if there is a pending invitation for the same email address
     *
     * @return ProjectInvitation
     */
    public function inviteUserByEmail($email, $role, array $environments = [], $force = false, array $permissions = [])
    {
        $data = [
            'email' => $email,
            'role' => $role,
            'force' => $force,
        ];
        if (!empty($permissions)) {
            $data['permissions'] = InvitationPermission::listForApi($permissions);
        }
        if (!empty($environments)) {
            $data['environments'] = InvitationEnvironment::listForApi($environments);
        }

        $request = $this->client->createRequest('post', $this->getLink('invitations'), ['json' => $data]);
        try {
            $data = self::send($request, $this->client);
        } catch (BadResponseException $e) {
            if ($e->getResponse() && $e->getResponse()->getStatusCode() === 409) {
                throw new AlreadyInvitedException(
                    'An invitation has already been created for this email address and role(s)',
                    $email,
                    $this,
                    $role,
                    $environments,
                    $permissions
                );
            }
            throw $e;
        }

        return new ProjectInvitation($data, $this->getLink('invitations'), $this->client);
    }

    /**
     * Get a single environment of the project.
     *
     * To get the project's default environment, use:
     * <code>
     * $defaultEnv = $project->getEnvironment($project->default_branch);
     * </code>
     *
     * @param string $id The environment ID.
     *
     * @return Environment|false
     */
    public function getEnvironment($id)
    {
        return Environment::get($id, $this->getLink('environments'), $this->client);
    }

    /**
     * @inheritdoc
     *
     * The accounts API does not (yet) return HAL links. This is a collection
     * of workarounds for that issue.
     */
    public function getLink($rel, $absolute = true)
    {
        /**
         * Require the API URL to be set for 'invitations' and 'access'.
         *
         * These endpoints require the external API proxy or gateway, or may
         * require it in the future.
         *
         * The parent method, via self::makeAbsoluteUrl(), ensures an
         * appropriate base URL is used otherwise.
         *
         * @see setApiUrl()
         * @see makeAbsoluteUrl()
         */
        if ($rel === 'invitations' || $rel === 'access') {
            if (!isset($this->urlViaGateway)) {
                throw new \RuntimeException('The API gateway URL must be set');
            }
        }

        // Use the HAL links available on the project.
        if ($this->hasLink($rel)) {
            return parent::getLink($rel, $absolute);
        }

        // If the 'self' link is not present then this might be a project
        // stub with an 'endpoint' property.
        if ($rel === 'self') {
            return $this->makeAbsoluteUrl($this->getProperty('endpoint'));
        }

        // If the '#ui' link is not present then this might be a project
        // stub with a 'uri' property.
        if ($rel === '#ui') {
            return $this->getProperty('uri');
        }

        if ($rel === '#manage-variables') {
            return $this->getUri() . '/variables';
        }

        return $this->getUri() . '/' . ltrim($rel, '#');
    }

    /**
     * Get a list of environments for the project.
     *
     * @param int $limit
     *   Limit the number of environments to return.
     * @param string|null $type
     *   Filter by environment type.
     * @param bool|null $active
     *   Filter by environment status (active or not).
     *
     * @return Environment[]
     */
    public function getEnvironments($limit = 0, $type = null, $active = null)
    {
        $options = [];
        if ($type !== null) {
            $options['query']['type'] = $type;
        }
        if ($active !== null) {
            $options['query']['active'] = $active ? 'true' : 'false';
        }

        return Environment::getCollection($this->getLink('environments'), $limit, $options, $this->client);
    }

    /**
     * Get a list of environment types for the project.
     *
     * @return EnvironmentType[]
     */
    public function getEnvironmentTypes()
    {
        return EnvironmentType::getCollection($this->getLink('environment-types'), 0, [], $this->client);
    }

    /**
     * Get an environment type.
     *
     * @param string $id
     *
     * @return EnvironmentType|false
     */
    public function getEnvironmentType($id)
    {
        return EnvironmentType::get($id, $this->getLink('environment-types'), $this->client);
    }

    /**
     * Get a list of domains for the project.
     *
     * @param int $limit
     *
     * @return Domain[]
     */
    public function getDomains($limit = 0)
    {
        return Domain::getCollection($this->getLink('domains'), $limit, [], $this->client);
    }

    /**
     * Get a single domain of the project.
     *
     * @param string $name
     *
     * @return Domain|false
     */
    public function getDomain($name)
    {
        return Domain::get($name, $this->getLink('domains'), $this->client);
    }

    /**
     * Add a domain to the project.
     *
     * @param string $name
     * @param array  $ssl
     *
     * @return Result
     */
    public function addDomain($name, array $ssl = [])
    {
        $body = ['name' => $name];
        if (!empty($ssl)) {
            $body['ssl'] = $ssl;
        }

        return Domain::create($body, $this->getLink('domains'), $this->client);
    }

    /**
     * Get a list of integrations for the project.
     *
     * @param int $limit
     *
     * @return Integration[]
     */
    public function getIntegrations($limit = 0)
    {
        return Integration::getCollection($this->getLink('integrations'), $limit, [], $this->client);
    }

    /**
     * Get a single integration of the project.
     *
     * @param string $id
     *
     * @return Integration|false
     */
    public function getIntegration($id)
    {
        return Integration::get($id, $this->getLink('integrations'), $this->client);
    }

    /**
     * Add an integration to the project.
     *
     * @param string $type
     * @param array $data
     *
     * @return Result
     */
    public function addIntegration($type, array $data = [])
    {
        $body = ['type' => $type] + $data;

        return Integration::create($body, $this->getLink('integrations'), $this->client);
    }

    /**
     * Returns whether the project is suspended.
     *
     * @return bool
     */
    public function isSuspended()
    {
        return !empty($this->data['subscription']['suspended'])
            || (isset($this->data['status']) && $this->data['status'] === 'suspended');
    }

    /**
     * Get a list of variables.
     *
     * @param int $limit
     *
     * @return ProjectLevelVariable[]
     */
    public function getVariables($limit = 0)
    {
        return ProjectLevelVariable::getCollection($this->getLink('#manage-variables'), $limit, [], $this->client);
    }

    /**
     * Set a variable.
     *
     * @param string $name
     *   The name of the variable to set.
     * @param mixed  $value
     *   The value of the variable to set.  If non-scalar it will be JSON-encoded automatically.
     * @param bool $json
     *   Whether this variable's value is JSON-encoded.
     * @param bool $visibleBuild
     *   Whether this variable should be exposed during the build phase.
     * @param bool $visibleRuntime
     *   Whether this variable should be exposed during deploy and runtime.
     * @param bool $sensitive
     *   Whether this variable's value should be readable via the API.
     *
     * @return Result
     */
    public function setVariable(
        $name,
        $value,
        $json = false,
        $visibleBuild = true,
        $visibleRuntime = true,
        $sensitive = false
    ) {
        // If $value isn't a scalar, assume it's supposed to be JSON.
        if (!is_scalar($value)) {
            $value = json_encode($value);
            $json = true;
        }
        $values = [
            'value' => $value,
            'is_json' => $json,
            'visible_build' => $visibleBuild,
            'visible_runtime' => $visibleRuntime];
        if ($sensitive) {
            $values['is_sensitive'] = $sensitive;
        }

        $existing = $this->getVariable($name);
        if ($existing) {
            return $existing->update($values);
        }

        $values['name'] = $name;

        return ProjectLevelVariable::create($values, $this->getLink('#manage-variables'), $this->client);
    }

    /**
     * Get a single variable.
     *
     * @param string $id
     *   The name of the variable to retrieve.
     * @return ProjectLevelVariable|false
     *   The variable requested, or False if it is not defined.
     */
    public function getVariable($id)
    {
        return ProjectLevelVariable::get($id, $this->getLink('#manage-variables'), $this->client);
    }

    /**
     * Get a list of certificates associated with this project.
     *
     * @return Certificate[]
     */
    public function getCertificates()
    {
        return Certificate::getCollection($this->getUri() . '/certificates', 0, [], $this->client);
    }

    /**
     * Get a single certificate.
     *
     * @param string $id
     *
     * @return Certificate|false
     */
    public function getCertificate($id)
    {
        return Certificate::get($id, $this->getUri() . '/certificates', $this->client);
    }

    /**
     * Add a certificate to the project.
     *
     * @param string $certificate
     * @param string $key
     * @param array  $chain
     *
     * @return Result
     */
    public function addCertificate($certificate, $key, array $chain = [])
    {
        $options = ['key' => $key, 'certificate' => $certificate, 'chain' => $chain];

        return Certificate::create($options, $this->getUri() . '/certificates', $this->client);
    }

    /**
     * Find the project base URL from another project resource's URL.
     *
     * @param string $url
     *
     * @return string
     */
    public static function getProjectBaseFromUrl($url)
    {
        if (preg_match('#/api/projects/([^/]+)#', $url, $matches)) {
            return Url::fromString($url)->combine('/api/projects/' . $matches[1])->__toString();
        }

        throw new \RuntimeException('Failed to find project ID from URL: ' . $url);
    }

    /**
     * Clear the project's build cache.
     *
     * @return Result
     */
    public function clearBuildCache()
    {
        return $this->runOperation('clear-build-cache');
    }

    /**
     * Returns system information about the project, e.g. the API version.
     *
     * @return System
     */
    public function systemInformation()
    {
        return System::get($this->getLink('#system'), '', $this->client);
    }

    /**
     * Returns the project's capabilities (features enabled by the billing system).
     *
     * @return Capabilities
     */
    public function getCapabilities()
    {
        $request = $this->client->createRequest('get', $this->getUri() . '/capabilities');
        $data = self::send($request, $this->client);

        return Capabilities::fromData($data);
    }

    /**
     * Returns the project settings.
     *
     * @return Settings
     */
    public function getSettings()
    {
        $url = $this->getUri() . '/settings';
        $request = $this->client->createRequest('get', $this->getUri() . '/settings');
        $data = self::send($request, $this->client);

        return new Settings($data, $url, $this->client);
    }
}
