<?php

namespace Platformsh\Client\Model;

use GuzzleHttp\ClientInterface;
use Platformsh\Client\Model\Ref\OrganizationRef;

/**
 * Represents a Platform.sh subscription.
 *
 * @property-read int    $id
 * @property-read string $status
 * @property-read string $owner
 * @property-read string $plan
 * @property-read int    $environments  Available environments.
 * @property-read int    $storage       Available storage (in MiB).
 * @property-read int    $user_licenses Number of users.
 * @property-read string $project_id
 * @property-read string $project_title
 * @property-read string $project_options
 * @property-read string $project_region
 * @property-read string $project_region_label
 * @property-read string $project_ui
 */
class Subscription extends ResourceWithReferences
{

    /**
     * @deprecated
     * @see \Platformsh\Client\PlatformClient::getPlans()
     *
     * @var string[]
     */
    public static $availablePlans = ['development', 'standard', 'medium', 'large'];

    /**
     * @deprecated
     * @see \Platformsh\Client\PlatformClient::getRegions()
     *
     * @var string[]
     */
    public static $availableRegions = ['eu.platform.sh', 'us.platform.sh'];

    protected static $required = ['project_region'];

    const STATUS_ACTIVE = 'active';
    const STATUS_REQUESTED = 'requested';
    const STATUS_PROVISIONING = 'provisioning';
    const STATUS_FAILED = 'provisioning failure';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_DELETED = 'deleted';

    /**
     * {@inheritdoc}
     *
     * @internal Use PlatformClient::createSubscription() to create a new subscription.
     *
     * @see \Platformsh\Client\PlatformClient::createSubscription()
     *
     * @return static
     */
    public static function create(array $body, $collectionUrl, ClientInterface $client)
    {
        $result = parent::create($body, $collectionUrl, $client);

        return new Subscription($result->getData(), $collectionUrl, $client);
    }

    /**
     * Wait for the subscription's project to be provisioned.
     *
     * @param callable  $onPoll   A function that will be called every time the
     *                            subscription is refreshed. It will be passed
     *                            one argument: the Subscription object.
     * @param int       $interval The polling interval, in seconds.
     */
    public function wait(callable $onPoll = null, $interval = 2)
    {
        while ($this->isPending()) {
            sleep($interval > 1 ? $interval : 1);
            $this->refresh();
            if ($onPoll !== null) {
                $onPoll($this);
            }
        }
    }

    /**
     * @inheritdoc
     */
    protected static function checkProperty($property, $value)
    {
        $errors = [];
        if ($property === 'storage' && $value < 1024) {
            $errors[] = "Storage must be at least 1024 MiB";
        }
        elseif ($property === 'activation_callback') {
            if (!isset($value['uri'])) {
                $errors[] = "A 'uri' key is required in the activation callback";
            }
            elseif (!filter_var($value['uri'], FILTER_VALIDATE_URL)) {
                $errors[] = 'Invalid URI in activation callback';
            }
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
     * Subscription::STATUS_FAILED, Subscription::STATUS_SUSPENDED,
     * or Subscription::STATUS_DELETED.
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
        if (!$this->hasLink('project') && !$this->isActive()) {
            throw new \BadMethodCallException('Inactive subscriptions do not have projects.');
        }
        $url = $this->getLink('project');
        return Project::get($url, null, $this->client);
    }

    /**
     * @inheritdoc
     */
    protected function setData(array $data)
    {
        $data = isset($data['subscriptions'][0]) ? $data['subscriptions'][0] : $data;
        $this->data = $data;
    }

    /**
     * @inheritdoc
     */
    public static function wrapCollection(array $data, $baseUrl, ClientInterface $client)
    {
        if (isset($data['items'])) {
            static::$collectionItemsKey = 'items';
        } elseif (isset($data['subscriptions'])) {
            static::$collectionItemsKey = 'subscriptions';
        }
        return parent::wrapCollection($data, $baseUrl, $client);
    }

    /**
     * @inheritdoc
     */
    protected function isOperationAvailable($op)
    {
        if ($op === 'edit') {
            return true;
        }

        return parent::isOperationAvailable($op);
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

    /**
     * Returns detailed information about the subscription's organization, if known.
     *
     * @return OrganizationRef|null
     */
    public function getOrganizationInfo()
    {
        if (isset($this->data['organization_id']) && isset($this->data['ref:organizations'][$this->data['organization_id']])) {
            return $this->data['ref:organizations'][$this->data['organization_id']];
        }
        return null;
    }
}
