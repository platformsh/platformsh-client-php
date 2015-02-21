<?php

namespace Platformsh\Client\Model;

class Project extends Resource
{

    /**
     * @return Environment[]
     */
    public function getEnvironments()
    {
        // @todo refactor getting json and resource from response automatically
        $data = $this->client->get($this->getUri() . '/environments')->json();
        return array_map(function ($element) {
           return new Environment($element, $this->client);
        }, $data);
    }

    public function getUri()
    {
        return $this->data['endpoint'];
    }
}
