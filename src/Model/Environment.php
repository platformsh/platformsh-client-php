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
     * @return array
     */
    public function branch($id, $title = null)
    {
        $body = array_filter(['name' => $id, 'title' => $title]);
        return $this->runOperation('branch', 'post', $body);
    }

    /**
     * Delete the environment.
     *
     * @throws \Exception
     *
     * @return array
     */
    public function delete()
    {
        if (isset($this->data['status']) && $this->data['status'] === 'active') {
            throw new \Exception('Active environments cannot be deleted');
        }
        return parent::delete();
    }

    /**
     * Deactivate the environment.
     *
     * @throws \Exception
     *
     * @return array
     */
    public function deactivate()
    {
        if (isset($this->data['status']) && $this->data['status'] === 'inactive') {
            throw new \Exception('Inactive environments cannot be deactivated');
        }
        return $this->runOperation('deactivate');
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
