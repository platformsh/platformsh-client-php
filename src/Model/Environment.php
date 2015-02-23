<?php

namespace Platformsh\Client\Model;

use Cocur\Slugify\Slugify;

class Environment extends Resource
{

    /**
     * Branch (create a new environment).
     *
     * @param string $title The title of the new environment.
     * @param string $id    The ID of the new environment. Leave blank to generate
     *                      automatically from the title.
     *
     * @return Activity
     */
    public function branch($title, $id = null)
    {
        $id = $id ?: $this->sanitizeId($title);
        if (!$this->validateId($id)) {
            throw new \InvalidArgumentException("Invalid environment ID: $id");
        }
        $body = ['name' => $id, 'title' => $title];

        return $this->runLongOperation('branch', 'post', $body);
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

    /**
     * Delete the environment.
     *
     * @param bool $deactivate Whether to deactivate the environment before
     *                         deleting it.
     *
     * @throws \Exception
     *
     * @return array
     */
    public function delete($deactivate = false)
    {
        if ($this->isActive()) {
            if (!$deactivate) {
                throw new \Exception('Active environments cannot be deleted');
            }
            // Deactivate the environment before deleting. Platform.sh will
            // queue the operations, so there should be no need to wait.
            $this->deactivate();
        }

        return parent::delete();
    }

    /**
     * @return bool
     */
    public function isActive()
    {
        return $this->data['status'] === 'active';
    }

    /**
     * Activate the environment.
     *
     * @throws \Exception
     *
     * @return Activity
     */
    public function activate()
    {
        if ($this->isActive()) {
            throw new \Exception('Active environments cannot be activated');
        }

        return $this->runLongOperation('activate');
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
        if (!$this->isActive()) {
            throw new \Exception('Inactive environments cannot be deactivated');
        }

        return $this->runLongOperation('deactivate');
    }

    /**
     * Merge an environment into its parent.
     *
     * @throws \Exception
     *
     * @return Activity
     */
    public function merge()
    {
        if (!$this->getProperty('parent')) {
            throw new \Exception('The environment does not have a parent, so it cannot be merged');
        }

        return $this->runLongOperation('merge');
    }

    /**
     * Synchronize an environment with its parent.
     *
     * @param bool $code
     * @param bool $data
     *
     * @throws \Exception
     *
     * @return Activity
     */
    public function synchronize($data = false, $code = false)
    {
        if (!$data && !$code) {
            throw new \Exception('Nothing to synchronize: you must specify $data or $code');
        }
        $body = ['synchronize_data' => $data, 'synchronize_code' => $code];

        return $this->runLongOperation('synchronize', 'post', $body);
    }

    /**
     * Create a backup of the environment.
     *
     * @throws \Exception
     *
     * @return Activity
     */
    public function backup()
    {
        return $this->runLongOperation('backup');
    }

    /**
     * Get a list of environment activities.
     *
     * @param int    $limit
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
     * Get a list of variables.
     *
     * @return Variable[]
     */
    public function getVariables()
    {
        return Variable::getCollection($this->getLink('#manage-variables'), [], $this->client);
    }

    /**
     * Set a variable
     *
     * @param string $name
     * @param mixed  $value
     * @param bool   $json
     *
     * @return Variable
     */
    public function setVariable($name, $value, $json = false)
    {
        if (!is_scalar($value)) {
            $value = json_encode($value);
            $json = true;
        }
        $values = ['value' => $value, 'is_json' => $json];
        $existing = $this->getVariable($name);
        if ($existing) {
            $existing->update($values);

            return $existing;
        }
        $values['name'] = $name;

        return Variable::create($values, $this->getLink('#manage-variables'), $this->client);
    }

    /**
     * Get a single variable.
     *
     * @param string $id
     *
     * @return Variable|false
     */
    public function getVariable($id)
    {
        return Variable::get($id, $this->getLink('#manage-variables'), $this->client);
    }
}
