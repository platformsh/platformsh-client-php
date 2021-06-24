<?php

namespace Platformsh\Client\Model;

use Cocur\Slugify\Slugify;
use GuzzleHttp\ClientInterface;
use Platformsh\Client\Exception\EnvironmentStateException;
use Platformsh\Client\Exception\OperationUnavailableException;
use Platformsh\Client\Model\Activities\HasActivitiesInterface;
use Platformsh\Client\Model\Activities\HasActivitiesTrait;
use Platformsh\Client\Model\Backups\BackupConfig;
use Platformsh\Client\Model\Backups\Policy;
use Platformsh\Client\Model\Deployment\EnvironmentDeployment;
use Platformsh\Client\Model\Deployment\Worker;
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
 * @property-read array       $backups
 *   The backup configuration. It's recommended to use getBackupConfig() instead
 *   of using this array directly.
 */
class Environment extends ApiResourceBase implements HasActivitiesInterface
{
    use HasActivitiesTrait;

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
     * @param string $app An application name. If there is no published URL for
     *                    this app name, the 'legacy' URL (without an app name)
     *                    will be returned.
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

        // Look for the first URL whose key starts with "$app:".
        \ksort($urls, SORT_NATURAL);
        foreach ($urls as $key => $url) {
            if (\strpos($key, $app . ':') === 0) {
                return $url;
            }
        }

        // Fall back to the legacy SSH URL.
        return $this->constructLegacySshUrl();
    }

    /**
     * Get the SSH URL for a worker.
     *
     * Workers themselves can be listed via getCurrentDeployment()->workers.
     *
     * @param Worker $worker
     *
     * @return string
     */
    public function getWorkerSshUrl(Worker $worker)
    {
        list($app, $worker) = explode('--', $worker->name, 2);

        $prefix = 'pf:ssh:';
        foreach ($this->data['_links'] as $rel => $link) {
            if ($rel === $prefix . $app && isset($link['href'])) {
                return $this->convertSshUrl($link['href'], '--' . $worker);
            }
        }

        throw new \RuntimeException(sprintf(
            'Unable to find the SSH URL for the app "%s" containing the worker "%s"',
            $app,
            $worker
        ));
    }

    /**
     * Get the SSH URL via the legacy 'ssh' link.
     *
     * @return string
     */
    private function constructLegacySshUrl()
    {
        if (!$this->hasLink('ssh')) {
            $id = $this->data['id'];
            if (!$this->isActive()) {
                throw new EnvironmentStateException("No SSH URL found for environment '$id'. It is not currently active.", $this);
            }
            throw new OperationUnavailableException("No SSH URL found for environment '$id'. You may not have permission to SSH.");
        }

        return $this->convertSshUrl($this->getLink('ssh'));
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
     * Branches an environment (creates a new environment as a child of the current one).
     *
     * The new environment's code will be the same as the parent environment.
     * Some other settings are typically inherited, such as variables.
     * Data is cloned from the parent environment (if $cloneParent is left as
     * true), including all data from services and file mounts.
     *
     * @param string $title       The title of the new environment.
     * @param string|null $id     The ID of the new environment. This will be the Git
     *                            branch name. Leave empty to generate automatically
     *                            from the title (not recommended).
     * @param bool   $cloneParent Whether to clone data from the parent
     *                            environment while branching.
     * @param string|null $type   The environment type, e.g. 'staging' or 'development'.
     *                            Leave this empty to use the default type for new
     *                            environments ('development' at the time of writing).
     *
     * @return Activity
     */
    public function branch($title, $id = null, $cloneParent = true, $type = null)
    {
        $id = $id ?: $this->sanitizeId($title);
        $body = ['name' => $id, 'title' => $title];
        if (!$cloneParent) {
            $body['clone_parent'] = false;
        }
        if ($type !== null) {
            $body['type'] = $type;
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
     * @param bool $code   Synchronize code.
     * @param bool $data   Synchronize data.
     * @param bool $rebase Synchronize code by rebasing instead of merging.
     *
     * @throws \InvalidArgumentException
     *
     * @return Activity
     */
    public function synchronize($data = false, $code = false, $rebase = false)
    {
        if (!$data && !$code) {
            throw new \InvalidArgumentException('Nothing to synchronize: you must specify $data or $code');
        }
        $body = [
            'synchronize_data' => $data,
            'synchronize_code' => $code,
        ];
        if ($rebase) {
            // @todo always add this (when the rebase option is GA)
            $body['rebase'] = $rebase;
        }

        return $this->runLongOperation('synchronize', 'post', $body);
    }

    /**
     * Create a backup of the environment.
     *
     * @param bool $unsafeAllowInconsistent
     *   Whether to allow performing an inconsistent backup (default: false).
     *   If true, this leaves the environment running and open to connections
     *   during the backup. So it reduces downtime, at the risk of backing up
     *   data in an inconsistent state.
     *
     * @return Activity
     */
    public function backup($unsafeAllowInconsistent = false)
    {
        $params = [];
        if ($unsafeAllowInconsistent) {
            $params['safe'] = false;
        }
        return $this->runLongOperation('backup', 'post', $params);
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
     * @param bool   $sensitive
     *
     * @return Result
     */
    public function setVariable(
        $name,
        $value,
        $json = false,
        $enabled = true,
        $sensitive = false
    )
    {
        if (!is_scalar($value)) {
            $value = json_encode($value);
            $json = true;
        }
        $values = ['value' => $value, 'is_json' => $json, 'is_enabled' => $enabled];
        if ($sensitive) {
            $values['is_sensitive'] = $sensitive;
        }
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
     * @param array $files
     *   An array of files that may be used in conjunction or in place of the
     *   repository parameter info.
     *
     * @return Activity
     */
    public function initialize($profile, $repository, $files=[])
    {
        $values = [
            'profile' => $profile,
            'repository' => $repository,
        ];

        if (!empty($files)) {
            $values['files'] = $files;
        }
    
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
     * @deprecated Users should now be invited via Project::inviteUserByEmail()
     *
     * @see Project::inviteUserByEmail()
     *
     * @return Result
     */
    public function addUser($user, $role, $byUuid = true)
    {
        $property = $byUuid ? 'user' : 'email';
        $body = [$property => $user, 'role' => $role];

        return EnvironmentAccess::create($body, $this->getLink('#manage-access'), $this->client);
    }

    /**
     * Redeploy the environment.
     *
     * @return Activity
     */
    public function redeploy()
    {
        return $this->runLongOperation('redeploy');
    }

    /**
     * Get a list of environment backups.
     *
     * @param int $limit
     *   Limit the number of backups to return.
     *
     * @return Backup[]
     */
    public function getBackups($limit = 0)
    {
        return Backup::getCollection($this->getUri() . '/backups', $limit, [], $this->client);
    }

    /**
     * Get the scheduled backup configuration for this environment.
     *
     * @return BackupConfig
     */
    public function getBackupConfig()
    {
        // In legacy versions the 'backups' key might not exist on the
        // environment.
        return BackupConfig::fromData($this->getProperty('backups', false) ?: []);
    }

    /**
     * Add a scheduled backup policy.
     *
     * @param \Platformsh\Client\Model\Backups\Policy $policy
     *
     * @return \Platformsh\Client\Model\Result
     */
    public function addBackupPolicy(Policy $policy)
    {
        $backups = isset($this->data['backups']) ? $this->data['backups'] : [];
        $backups['schedule'][] = [
            'interval' => $policy->getInterval(),
            'count' => $policy->getCount(),
        ];
        if (!isset($backups['manual_count'])) {
            $backups['manual_count'] = 3;
        }

        return $this->update(['backups' => $backups]);
    }

    /**
     * Runs a source operation.
     *
     * @param string $name
     *   The operation name.
     * @param array  $variables
     *   Variables to define during the operation, as a nested associative
     *   array, e.g. ['env'=>['foo'=>'bar']]
     *
     * @return \Platformsh\Client\Model\Result
     */
    public function runSourceOperation(string $name, array $variables = []): Result
    {
        return $this->runOperation('source-operation', 'post', [
            'operation' => $name,
            'variables' => (object) $variables,
        ]);
    }
}
