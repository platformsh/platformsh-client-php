<?php

namespace Platformsh\Client\Model;

use Cocur\Slugify\Slugify;

/**
 * A Platform.sh environment.
 *
 * Environments correspond to project Git branches.
 *
 * @property-read string $id
 * @property-read string $status
 * @property-read string $head_commit
 * @property-read string $name
 * @property-read string $parent
 * @property-read string $title
 * @property-read string $created_at
 * @property-read string $updated_at
 * @property-read string $project
 * @property-read bool   $is_dirty
 * @property-read bool   $enable_smtp
 * @property-read bool   $has_code
 * @property-read string $deployment_target
 * @property-read array  $http_access
 * @property-read bool   $is_main
 */
class Environment extends Resource
{

    /**
     * Get the SSH URL for the environment.
     *
     * @param string $app An application name.
     *
     * @throws \Exception
     *
     * @return string
     */
    public function getSshUrl($app = '')
    {
        if (!$this->hasLink('ssh')) {
            $id = $this->data['id'];
            throw new \Exception("The environment $id does not have an SSH URL.");
        }

        $sshUrl = parse_url($this->getLink('ssh'));
        $host = $sshUrl['host'];
        $user = $sshUrl['user'];

        if ($app) {
            $user .= '--' . $app;
        }

        return $user . '@' . $host;
    }

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
     * @throws \InvalidArgumentException
     *
     * @return Activity
     */
    public function synchronize($data = false, $code = false)
    {
        if (!$data && !$code) {
            throw new \InvalidArgumentException('Nothing to synchronize: you must specify $data or $code');
        }
        $body = ['synchronize_data' => $data, 'synchronize_code' => $code];

        return $this->runLongOperation('synchronize', 'post', $body);
    }

    /**
     * Create a backup of the environment.
     *
     * @return Activity
     */
    public function backup()
    {
        return $this->runLongOperation('backup');
    }

    /**
     * Get a single environment activity.
     *
     * @param string $id
     *
     * @return Activity|false
     */
    public function getActivity($id)
    {
        return Activity::get($id, $this->getUri() . '/activities', $this->client);
    }

    /**
     * Get the activity from the previous operation.
     *
     * @return Activity|false
     */
    public function getLastActivity()
    {
        if (!isset($this->data['_embedded']['activities'][0])) {
            return false;
        }
        return Activity::wrap($this->data['_embedded']['activities'][0], $this->client);
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
        if ($type !== null) {
            $options['query']['type'] = $type;
        }

        return Activity::getCollection($this->getUri() . '/activities', $limit, $options, $this->client);
    }

    /**
     * Get a list of variables.
     *
     * @param int $limit
     *
     * @return Variable[]
     */
    public function getVariables($limit = 0)
    {
        return Variable::getCollection($this->getLink('#manage-variables'), $limit, [], $this->client);
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
