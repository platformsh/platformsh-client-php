<?php

namespace Platformsh\Client\Model;

class Environment extends Resource
{

    /**
     * @param int $limit
     * @param string $type
     *
     * @return EnvironmentActivity[]
     */
    public function getActivities($limit = 0, $type = null)
    {
        $options = [];
        if ($limit) {
            $options['query']['count'] = $limit;
        }
        if ($type) {
            $options['query']['type'] = $type;
        }
        return EnvironmentActivity::getCollection($this->getUri() . '/activities', $options, $this->client);
    }
}
