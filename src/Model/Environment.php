<?php

namespace Platformsh\Client\Model;

class Environment extends Resource
{

    /**
     * Branch (create a new environment).
     *
     * @param string $id
     * @param string $title
     *
     * @return static
     */
    public function branch($id, $title = null)
    {
        $body = array_filter(['name' => $id, 'title' => $title]);
        $this->runOperation('branch', 'post', $body);
    }

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
