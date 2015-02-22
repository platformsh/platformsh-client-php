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
     * @return Activity
     */
    public function branch($title, $id = null)
    {
        $id = $id ?: $this->sanitizeId($title);
        if (!$this->validateId($id)) {
            throw new \InvalidArgumentException("Invalid branch ID: $id");
        }
        return $this->runLongOperation('branch', 'post', [
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
     * @return Activity
     */
    public function deactivate()
    {
        if (isset($this->data['status']) && $this->data['status'] === 'inactive') {
            throw new \Exception('Inactive environments cannot be deactivated');
        }
        return $this->runLongOperation('deactivate');
    }

    /**
     * @param int $limit
     * @param string $type
     *
     * @return Activity[]
     */
    public function getActivities($limit = 0, $type = null)
    {
        $options = [];
        if ($limit) {
            $options['query']['count'] = $limit;
        }
        if ($type !== null) {
            $options['query']['type'] = $type;
        }
        return Activity::getCollection($this->getUri() . '/activities', $options, $this->client);
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

    /**
     * @param string $id
     *
     * @return bool
     */
    public static function validateId($id)
    {
        return strlen($id) <= 32 && preg_match('/^[a-z0-9\-]+$/i', $id);
    }
}
