<?php

namespace Platformsh\Client\Model;

class Project extends Resource
{

    /**
     * @param string $id
     *
     * @return Environment|false
     */
    public function getEnvironment($id)
    {
        return Environment::get($id, $this->getUri() . '/environments', $this->client);
    }

    /**
     * @return Environment[]
     */
    public function getEnvironments()
    {
        return Environment::getCollection($this->getUri() . '/environments', [], $this->client);
    }

    public function getUri()
    {
        if (!empty($this->data['_url'])) {
            return $this->data['_url'];
        }
        return $this->data['endpoint'];
    }
}
