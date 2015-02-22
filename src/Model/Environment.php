<?php

namespace Platformsh\Client\Model;

use Cocur\Slugify\Slugify;

class Environment extends Resource
{

    /**
     * Branch (create a new environment).
     *
     * @param string $title The title of the new environment.
     * @param string $id The ID of the new environment. Leave blank to generate
     *                   automatically from the title.
     *
     * @return array
     */
    public function branch($title, $id = null)
    {
        $id = $id ?: $this->sanitizeId($title);
        return $this->runOperation('branch', 'post', [
          'name' => $id,
          'title' => $title,
        ]);
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

    /**
     * @param string $proposed
     *
     * @return string
     */
    public static function sanitizeId($proposed)
    {
        $slugify = new Slugify();
        return substr($slugify->slugify($proposed), 0, 32);
    }
}
