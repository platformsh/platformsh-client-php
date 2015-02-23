<?php

namespace Platformsh\Client\Model;

class Project extends Resource
{

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
    protected function getUri()
    {
        if (!empty($this->data['_full'])) {
            return parent::getUri();
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
        $options = [];
        if ($limit) {
            $options['query']['count'] = $limit;
        }

        return Environment::getCollection($this->getUri() . '/environments', $options, $this->client);
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
        $options = [];
        if ($limit) {
            $options['query']['count'] = $limit;
        }

        return Domain::getCollection($this->getUri() . '/domains', $options, $this->client);
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
     *
     * @return Domain
     */
    public function addDomain($name, $wildcard = false)
    {
        $body = ['name' => $name, 'wildcard' => $wildcard];

        return Domain::create($this->getUri() . '/domains', $body, $this->client);
    }
}
