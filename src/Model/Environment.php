<?php

namespace Platformsh\Client\Model;

use Cocur\Slugify\Slugify;
use Platformsh\Client\Exception\EnvironmentStateException;
use Platformsh\Client\Exception\OperationUnavailableException;
use Platformsh\Client\Model\Deployment\EnvironmentDeployment;
use Platformsh\Client\Model\Git\Commit;

/**
 * A Platform.sh environment.
 *
 * Environments correspond to project Git branches.
 *
 * @property-read string      $id
 *   The primary ID of the environment. This is the same as the 'name' property.
 * @property-read string      $status
 *   The status of the environment: active, inactive, or dirty.
 * @property-read string      $head_commit
 *   The SHA-1 hash identifying the Git commit at the branch's HEAD.
 * @property-read string      $name
 *   The Git branch name of the environment.
 * @property-read string|null $parent
 *   The ID (or name) of the parent environment, or null if there is no parent.
 * @property-read string      $machine_name
 *   A slug of the ID, sanitized for use in domain names, with a random suffix
 *   (for uniqueness within a project). Can contain lower-case letters, numbers,
 *   and hyphens.
 * @property-read string      $title
 *   A human-readable title or label for the environment.
 * @property-read string      $created_at
 *   The date the environment was created (ISO 8601).
 * @property-read string      $updated_at
 *   The date the environment was last updated (ISO 8601).
 * @property-read string      $project
 *   The project ID for the environment.
 * @property-read bool        $is_dirty
 *   Whether the environment is in a 'dirty' state: deploying or broken.
 * @property-read bool        $enable_smtp
 *   Whether outgoing emails should be enabled for an environment.
 * @property-read bool        $has_code
 *   Whether the environment has any code committed.
 * @property-read string      $deployment_target
 *   The deployment target for an environment (always 'local' for now).
 * @property-read array       $http_access
 *   HTTP access control for an environment. An array containing at least
 *   'is_enabled' (bool), 'addresses' (array), and 'basic_auth' (array).
 * @property-read bool        $is_main
 *   Whether the environment is the main, production one.
 */
class Environment extends Resource
{
    /**
     * Get the current deployment of this environment.
     *
     * @throws \RuntimeException if no current deployment is found.
     *
     * @return EnvironmentDeployment
     */
    public function getCurrentDeployment()
    {
        $deployment = EnvironmentDeployment::get('current', $this->getUri() . '/deployments', $this->client);
        if (!$deployment) {
            throw new EnvironmentStateException('Current deployment not found', $this);
        }

        return $deployment;
    }

    /**
     * Get the Git commit for the HEAD of this environment.
     *
     * @return Commit|false
     */
    public function getHeadCommit()
    {
        $base = Project::getProjectBaseFromUrl($this->getUri()) . '/git/commits';

        return Commit::get($this->head_commit, $base, $this->client);
    }

    /**
     * Get the SSH URL for the environment.
     *
     * @param string $app An application name.
     *
     * @throws EnvironmentStateException
     * @throws OperationUnavailableException
     *
     * @return string
     */
    public function getSshUrl($app = '')
    {
        $urls = $this->getSshUrls();
        if (isset($urls[$app])) {
            return $urls[$app];
        }

        return $this->constructLegacySshUrl($app);
    }

    /**
     * Get the SSH URL via the legacy 'ssh' link.
     *
     * @param string $app
     *
     * @return string
     */
    private function constructLegacySshUrl($app)
    {
        if (!$this->hasLink('ssh')) {
            $id = $this->data['id'];
            if (!$this->isActive()) {
                throw new EnvironmentStateException("No SSH URL found for environment '$id'. It is not currently active.", $this);
            }
            throw new OperationUnavailableException("No SSH URL found for environment '$id'. You may not have permission to SSH.");
        }

        $suffix = $app ? '--' . $app : '';

        return $this->convertSshUrl($this->getLink('ssh'), $suffix);
    }

    /**
     * Convert a full SSH URL (with schema) into a normal SSH connection string.
     *
     * @param string $url             The URL (starting with ssh://).
     * @param string $username_suffix A suffix to append to the username.
     *
     * @return string
     */
    private function convertSshUrl($url, $username_suffix = '')
    {
        $parsed = parse_url($url);
        if (!$parsed) {
            throw new \InvalidArgumentException('Invalid URL: ' . $url);
        }

        return $parsed['user'] . $username_suffix . '@' . $parsed['host'];
    }

    /**
     * Returns a list of SSH URLs, keyed by app name.
     *
     * @return string[]
     */
    public function getSshUrls()
    {
        $prefix = 'pf:ssh:';
        $prefixLength = strlen($prefix);
        $sshUrls = [];
        foreach ($this->data['_links'] as $rel => $link) {
            if (strpos($rel, $prefix) === 0 && isset($link['href'])) {
                $sshUrls[substr($rel, $prefixLength)] = $this->convertSshUrl($link['href']);
            }
        }
        if (empty($sshUrls) && $this->hasLink('ssh')) {
            $sshUrls[''] = $this->convertSshUrl($this->getLink('ssh'));
        }

        return $sshUrls;
    }

    /**
     * Get the public URL for the environment.
     *
     * @throws EnvironmentStateException
     *
     * @deprecated You should use routes to get the correct URL(s)
     * @see        self::getRouteUrls()
     *
     * @return string
     */
    public function getPublicUrl()
    {
        if (!$this->hasLink('public-url')) {
            $id = $this->data['id'];
            if (!$this->isActive()) {
                throw new EnvironmentStateException("No public URL found for environment '$id'. It is not currently active.", $this);
            }
            throw new OperationUnavailableException("No public URL found for environment '$id'.");
        }

        return $this->getLink('public-url');
    }

    /**
     * Branch (create a new environment).
     *
     * @param string $title       The title of the new environment.
     * @param string $id          The ID of the new environment. This will be the Git
     *                            branch name. Leave blank to generate automatically
     *                            from the title.
     * @param bool   $cloneParent Whether to clone data from the parent
     *                            environment while branching.
     *
     * @return Activity
     */
    public function branch($title, $id = null, $cloneParent = true)
    {
        $id = $id ?: $this->sanitizeId($title);
        $body = ['name' => $id, 'title' => $title];
        if (!$cloneParent) {
            $body['clone_parent'] = false;
        }

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
     * Validate an environment ID.
     *
     * @deprecated This is no longer necessary and will be removed in future
     * versions.
     *
     * @param string $id
     *
     * @return bool
     */
    public static function validateId($id)
    {
        return !empty($id);
    }

    /**
     * Delete the environment.
     *
     * @throws EnvironmentStateException
     *
     * @return Result
     */
    public function delete()
    {
        if ($this->isActive()) {
            throw new EnvironmentStateException('Active environments cannot be deleted', $this);
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
     * @throws EnvironmentStateException
     *
     * @return Activity
     */
    public function activate()
    {
        if ($this->isActive()) {
            throw new EnvironmentStateException('Active environments cannot be activated', $this);
        }

        return $this->runLongOperation('activate');
    }

    /**
     * Deactivate the environment.
     *
     * @throws EnvironmentStateException
     *
     * @return Activity
     */
    public function deactivate()
    {
        if (!$this->isActive()) {
            throw new EnvironmentStateException('Inactive environments cannot be deactivated', $this);
        }

        return $this->runLongOperation('deactivate');
    }

    /**
     * Merge an environment into its parent.
     *
     * @throws OperationUnavailableException
     *
     * @return Activity
     */
    public function merge()
    {
        if (!$this->getProperty('parent')) {
            throw new OperationUnavailableException('The environment does not have a parent, so it cannot be merged');
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
     * Get a list of environment activities.
     *
     * @param int    $limit
     *   Limit the number of activities to return.
     * @param string $type
     *   Filter activities by type.
     * @param int    $startsAt
     *   A UNIX timestamp for the maximum created date of activities to return.
     *
     * @return Activity[]
     */
    public function getActivities($limit = 0, $type = null, $startsAt = null)
    {
        $options = [];
        if ($type !== null) {
            $options['query']['type'] = $type;
        }
        if ($startsAt !== null) {
            $options['query']['starts_at'] = Activity::formatStartsAt($startsAt);
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
     * @param bool   $enabled
     *
     * @return Result
     */
    public function setVariable($name, $value, $json = false, $enabled = true)
    {
        if (!is_scalar($value)) {
            $value = json_encode($value);
            $json = true;
        }
        $values = ['value' => $value, 'is_json' => $json, 'is_enabled' => $enabled];
        $existing = $this->getVariable($name);
        if ($existing) {
            return $existing->update($values);
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

    /**
     * Get the environment's routes configuration.
     *
     * @see self::getRouteUrls()
     *
     * @return Route[]
     */
    public function getRoutes()
    {
        return Route::getCollection($this->getLink('#manage-routes'), 0, [], $this->client);
    }

    /**
     * Get the resolved URLs for the environment's routes.
     *
     * @return string[]
     */
    public function getRouteUrls()
    {
        $routes = [];
        if (isset($this->data['_links']['pf:routes'])) {
            foreach ($this->data['_links']['pf:routes'] as $route) {
                $routes[] = $route['href'];
            }
        }

        return $routes;
    }

    /**
     * Initialize the environment from an external repository.
     *
     * This can only work when the repository is empty.
     *
     * @param string $profile
     *   The name of the profile. This is shown in the resulting activity log.
     * @param string $repository
     *   A repository URL, optionally followed by an '@' sign and a branch name,
     *   e.g. 'git://github.com/platformsh/platformsh-examples.git@drupal/7.x'.
     *   The default branch is 'master'.
     *
     * @return Activity
     */
    public function initialize($profile, $repository)
    {
        $values = [
            'profile' => $profile,
            'repository' => $repository,
        ];

        return $this->runLongOperation('initialize', 'post', $values);
    }

    /**
     * Get a user's access to this environment.
     *
     * @param string $uuid
     *
     * @return EnvironmentAccess|false
     */
    public function getUser($uuid)
    {
        return EnvironmentAccess::get($uuid, $this->getLink('#manage-access'), $this->client);
    }

    /**
     * Get the users with access to this environment.
     *
     * @return EnvironmentAccess[]
     */
    public function getUsers()
    {
        return EnvironmentAccess::getCollection($this->getLink('#manage-access'), 0, [], $this->client);
    }

    /**
     * Add a new user to the environment.
     *
     * @param string $user   The user's UUID or email address (see $byUuid).
     * @param string $role   One of EnvironmentAccess::$roles.
     * @param bool   $byUuid Set true (default) if $user is a UUID, or false if
     *                       $user is an email address.
     *
     * Note that for legacy reasons, the default for $byUuid is false for
     * Project::addUser(), but true for Environment::addUser().
     *
     * @return Result
     */
    public function addUser($user, $role, $byUuid = true)
    {
        $property = $byUuid ? 'user' : 'email';
        $body = [$property => $user, 'role' => $role];

        return EnvironmentAccess::create($body, $this->getLink('#manage-access'), $this->client);
    }
}
