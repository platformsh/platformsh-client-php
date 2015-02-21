<?php

namespace Platformsh\Client\Model;

class Environment extends Resource
{

    /**
     * @return EnvironmentActivity[]
     */
    public function getActivities()
    {
        // @todo refactor getting json and resource from response automatically
        $data = $this->client->get($this->getUri() . '/activities')->json();
        return array_map(function ($element) {
            return new EnvironmentActivity($element, $this->client);
        }, $data);
    }
}
