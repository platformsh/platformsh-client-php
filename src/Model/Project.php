<?php

namespace Platformsh\Client\Model;

class Project extends Resource
{

    /**
     * @return Environment[]
     */
    public function getEnvironments()
    {
        // @todo sort out base url
        // @todo refactor getting json and resource from response automatically
        $data = $this->client->get($this->hal->getUri() . '/environments')->json();
        return array_map(function ($element) {
           return new Environment($element, $this->client);
        }, $data);
    }

    /**
     * @inheritdoc
     */
    protected function determineUri(array $data)
    {
        return $data['endpoint'];
    }
}
