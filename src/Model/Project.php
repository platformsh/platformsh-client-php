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
     * Get the users associated with a project.
     *
     * @return ProjectUser[]
     */
    public function getUsers()
    {
        return ProjectUser::getCollection($this->getUri() . '/access', 0, [], $this->client);
    }

    /**
     * Add a new user to a project.
     *
     * @param string $email An email address.
     * @param string $role  One of User::ROLE_ADMIN or User::ROLE_VIEWER.
     *
     * @return ProjectUser
     */
    public function addUser($email, $role)
    {
        $body = ['email' => $email, 'role' => $role];

        return ProjectUser::create($body, $this->getUri() . '/access', $this->client);
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
        return Environment::get($id, $this->getUri() . '/environments', $this->client);
    }

    /**
     * @inheritdoc
     *
     * The accounts API does not (yet) return HAL links. Stub projects contain
     * an 'endpoint' property.
     */
    public function getUri($absolute = false)
    {
        if ($this->hasLink('self')) {
            return $this->getLink('self', $absolute);
        }

        return $this->data['endpoint'];
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
        return Environment::getCollection($this->getUri() . '/environments', $limit, [], $this->client);
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
        return Domain::getCollection($this->getUri() . '/domains', $limit, [], $this->client);
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
        return Domain::get($name, $this->getUri() . '/domains', $this->client);
    }

    /**
     * Add a domain to the project.
     *
     * @param string $name
     * @param bool   $wildcard
     * @param array  $ssl
     *
     * @return Domain
     */
    public function addDomain($name, $wildcard = false, array $ssl = [])
    {
        $body = ['name' => $name, 'wildcard' => $wildcard];
        if (!empty($ssl)) {
            $body['ssl'] = $ssl;
        }

        return Domain::create($body, $this->getUri() . '/domains', $this->client);
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
        return Integration::getCollection($this->getUri() . '/integrations', $limit, [], $this->client);
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
        return Integration::get($id, $this->getUri() . '/integrations', $this->client);
    }

    /**
     * Add an integration to the project.
     *
     * @param string $type
     * @param array $data
     *
     * @return Integration
     */
    public function addIntegration($type, array $data = [])
    {
        $body = ['type' => $type] + $data;

        return Integration::create($body, $this->getUri() . '/integrations', $this->client);
    }

    /**
     * Get project routes.
     *
     * @return Route[]
     */
    public function getRoutes()
    {
        return ProjectUser::getCollection($this->getUri() . '/routes', 0, [], $this->client);
    }
}
