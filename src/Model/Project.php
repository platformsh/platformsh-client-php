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
    public function getUri()
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
}
