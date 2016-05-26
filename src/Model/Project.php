<?php

namespace Platformsh\Client\Model;

/**
 * A Platform.sh project.
 *
 * @property-read string $id
 * @property-read string $title
 * @property-read string $created_at
 * @property-read string $updated_at
 * @property-read string $owner
 */
class Project extends Resource
{
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
     * @return int
     */
    public function getSubscriptionId()
    {
        if ($this->hasProperty('subscription_id')) {
            return $this->getProperty('subscription_id');
        }

        if (isset($this->data['subscription']['license_uri'])) {
            return basename($this->data['subscription']['license_uri']);
        }

        throw new \RuntimeException('Subscription ID not found');
    }

    /**
     * Get the Git URL for the project.
     *
     * @param bool $lazyLoad
     *
     * @return string
     */
    public function getGitUrl($lazyLoad = true)
    {
        // The collection doesn't provide a Git URL, but it does provide the
        // right host, so the URL can be calculated.
        if (!$this->hasProperty('repository', $lazyLoad)) {
            $host = parse_url($this->getUri(), PHP_URL_HOST);

            return "{$this->id}@git.{$host}:{$this->id}.git";
        }
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
     * @param string $email An email address.
     * @param string $role  One of ProjectUser::$roles.
     *
     * @return Result
     */
    public function addUser($email, $role)
    {
        $body = ['email' => $email, 'role' => $role];

        return ProjectAccess::create($body, $this->getLink('access'), $this->client);
    }

    /**
     * Get a single environment of the project.
     *
     * @param string $id
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
        if ($this->hasLink($rel)) {
            return parent::getLink($rel, $absolute);
        }

        if ($rel === 'self') {
            return $this->getProperty('endpoint');
        }

        if ($rel === '#ui') {
            return $this->getProperty('uri');
        }

        return $this->getUri() . '/' . ltrim($rel, '#');
    }

    /**
     * Get a list of environments for the project.
     *
     * @param int $limit
     *
     * @return Environment[]
     */
    public function getEnvironments($limit = 0)
    {
        return Environment::getCollection($this->getLink('environments'), $limit, [], $this->client);
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
     * Get a single project activity.
     *
     * @param string $id
     *
     * @return Activity|false
     */
    public function getActivity($id)
    {
        return Activity::get($id, $this->getUri() . '/activities', $this->client);
    }

    /**
     * Get a list of project activities.
     *
     * @param int $limit
     *   Limit the number of activities to return.
     * @param string $type
     *   Filter activities by type.
     * @param int $startsAt
     *   A UNIX timestamp for the maximum created date of activities to return.
     *
     * @return Activity[]
     */
    public function getActivities($limit = 0, $type = null, $startsAt = null)
    {
        $options = [];
        if ($type !== null) {
            $options['query']['type'] = $type;
        }
        if ($startsAt !== null) {
            $options['query']['starts_at'] = date('c', $startsAt);
        }

        return Activity::getCollection($this->getUri() . '/activities', $limit, $options, $this->client);
    }
}
