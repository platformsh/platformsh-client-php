<?php

namespace Platformsh\Client\Model;

use GuzzleHttp\ClientInterface;

/**
 * Represents a Platform.sh subscription.
 *
 * @property-read string $status
 * @property-read string $owner
 * @property-read string $plan
 * @property-read int    $environments  Available environments.
 * @property-read int    $storage       Available storage (in MiB).
 * @property-read int    $user_licenses Number of users.
 * @property-read string $project_id
 * @property-read string $project_title
 * @property-read string $project_cluster
 * @property-read string $project_cluster_label
 */
class Subscription extends Resource
{

    public static $availablePlans = ['development', 'standard', 'medium', 'large'];
    public static $availableClusters = ['eu_west', 'us_east'];

    const STATUS_ACTIVE = 'Active';
    const STATUS_REQUESTED = 'Requested';
    const STATUS_PROVISIONING = 'Provisioning';
    const STATUS_SUSPENDED = 'Suspended';
    const STATUS_DELETED = 'Deleted';

    /**
     * @inheritdoc
     */
    public static function getRequired()
    {
        return ['project_cluster', 'plan', 'project_title', 'storage', 'environments'];
    }

    /**
     * @inheritdoc
     */
    protected static function checkProperty($property, $value)
    {
        $errors = [];
        if ($property === 'plan' && !in_array($value, self::$availablePlans)) {
            $errors[] = "Plan not found: " . $value;
        }
        elseif ($property === 'cluster' && !in_array($value, self::$availableClusters)) {
            $errors[] = "Cluster not found: " . $value;
        }
        elseif ($property === 'storage' && $value < 1024) {
            $errors[] = "Storage must be at least 1024 MiB";
        }
        return $errors;
    }

    /**
     * Check whether the subscription is pending (requested or provisioning).
     *
     * @return bool
     */
    public function isPending()
    {
        $status = $this->getStatus();
        return $status === self::STATUS_PROVISIONING || $status === self::STATUS_REQUESTED;
    }

    /**
     * Find whether the subscription is active.
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->getStatus() === self::STATUS_ACTIVE;
    }

    /**
     * Get the subscription status.
     *
     * This could be one of Subscription::STATUS_ACTIVE,
     * Subscription::STATUS_REQUESTED, Subscription::STATUS_PROVISIONING,
     * Subscription::STATUS_SUSPENDED, or Subscription::STATUS_DELETED.
     *
     * @return string
     */
    public function getStatus()
    {
        return $this->getProperty('status');
    }

    /**
     * Get the account for the project's owner.
     *
     * @return Account|false
     */
    public function getOwner()
    {
        $uuid = $this->getProperty('owner');
        $url = $this->makeAbsoluteUrl('/api/users', $this->getLink('project'));
        return Account::get($uuid, $url, $this->client);
    }

    /**
     * Get the project associated with this subscription.
     *
     * @return Project|false
     */
    public function getProject()
    {
        $url = $this->getLink('project');
        return Project::get($url, null, $this->client);
    }

    /**
     * @inheritdoc
     */
    public static function wrap(array $data, $baseUrl, ClientInterface $client)
    {
        $data = isset($data['subscriptions'][0]) ? $data['subscriptions'][0] : [];
        return parent::wrap($data, $baseUrl, $client);
    }

    /**
     * @inheritdoc
     */
    public static function wrapCollection(array $data, $baseUrl, ClientInterface $client)
    {
        $data = isset($data['subscriptions']) ? $data['subscriptions'] : [];
        return parent::wrapCollection($data, $baseUrl, $client);
    }

    /**
     * @inheritdoc
     */
    public function operationAvailable($op)
    {
        if ($op === 'edit') {
            return true;
        }
        return parent::operationAvailable($op);
    }

    /**
     * @inheritdoc
     */
    public function getLink($rel, $absolute = false)
    {
        if ($rel === '#edit') {
            return $this->getUri($absolute);
        }
        return parent::getLink($rel, $absolute);
    }
}
