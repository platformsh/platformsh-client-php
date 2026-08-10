<?php

declare(strict_types=1);

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
    public const STATUS_ACTIVE = 'active';

    public const STATUS_REQUESTED = 'requested';

    public const STATUS_PROVISIONING = 'provisioning';

    public const STATUS_FAILED = 'provisioning failure';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_DELETED = 'deleted';

    /**
     * List of available plans.
     *
     * @deprecated
     * @see \Platformsh\Client\PlatformClient::getPlans()
     *
     * @var string[]
     */
    public static array $availablePlans = ['development', 'standard', 'medium', 'large'];

    /**
     * List of available regions.
     *
     * @deprecated
     * @see \Platformsh\Client\PlatformClient::getRegions()
     *
     * @var string[]
     */
    public static array $availableRegions = ['eu-3.platform.sh', 'us-2.platform.sh'];

    protected static array $required = ['project_region'];

    /**
     * @internal Use PlatformClient::createSubscription() to create a new subscription.
     *
     * @see \Platformsh\Client\PlatformClient::createSubscription()
     */
    public static function create(array $body, string $collectionUrl, ClientInterface $client): static
    {
        $result = parent::create($body, $collectionUrl, $client);

        return new self($result->getData(), $collectionUrl, $client);
    }

    /**
     * Wait for the subscription's project to be provisioned.
     *
     * @param callable|null $onPoll A function that will be called every time the
     *                            subscription is refreshed. It will be passed
     *                            one argument: the Subscription object.
     * @param int $interval The polling interval, in seconds.
     */
    public function wait(?callable $onPoll = null, int $interval = 2): void
    {
        while ($this->isPending()) {
            sleep(max($interval, 1));
            $this->refresh();
            if ($onPoll !== null) {
                $onPoll($this);
            }
        }
    }

    /**
     * Check whether the subscription is pending (requested or provisioning).
     */
    public function isPending(): bool
    {
        $status = $this->getStatus();
        return $status === self::STATUS_PROVISIONING || $status === self::STATUS_REQUESTED;
    }

    /**
     * Find whether the subscription is active.
     */
    public function isActive(): bool
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
     */
    public function getStatus(): string
    {
        return $this->getProperty('status');
    }

    /**
     * Get the account for the project's owner.
     */
    public function getOwner(): false|Account
    {
        $uuid = $this->getProperty('owner');
        $url = $this->makeAbsoluteUrl('/api/users', $this->getLink('project'));
        return Account::get($uuid, $url, $this->client);
    }

    /**
     * Get the project associated with this subscription.
     */
    public function getProject(): Project|false
    {
        if (! $this->hasLink('project') && ! $this->isActive()) {
            throw new \BadMethodCallException('Inactive subscriptions do not have projects.');
        }
        $url = $this->getLink('project');
        return Project::get($url, null, $this->client);
    }

    public static function wrapCollection(array|Collection $data, string $baseUrl, ClientInterface $client): array
    {
        $dataArray = $data instanceof Collection ? $data->getData() : $data;
        if (isset($dataArray['items'])) {
            static::$collectionItemsKey = 'items';
        } elseif (isset($dataArray['subscriptions'])) {
            static::$collectionItemsKey = 'subscriptions';
        }
        return parent::wrapCollection($data, $baseUrl, $client);
    }

    public function operationAvailable(string $op, bool $refreshDuringCheck = false): bool
    {
        if ($op === 'edit') {
            return true;
        }

        return parent::operationAvailable($op, $refreshDuringCheck);
    }

    public function getLink(string $rel, bool $absolute = false): string
    {
        if ($rel === '#edit') {
            return $this->getUri($absolute);
        }
        return parent::getLink($rel, $absolute);
    }

    /**
     * Returns detailed information about the subscription's organization, if known.
     */
    public function getOrganizationInfo(): ?OrganizationRef
    {
        if (isset($this->data['organization_id']) && isset($this->data['ref:organizations'][$this->data['organization_id']])) {
            return $this->data['ref:organizations'][$this->data['organization_id']];
        }
        return null;
    }

    protected static function checkProperty(string $property, mixed $value): array
    {
        $errors = [];
        if ($property === 'storage' && $value < 1024) {
            $errors[] = 'Storage must be at least 1024 MiB';
        } elseif ($property === 'activation_callback') {
            if (! isset($value['uri'])) {
                $errors[] = "A 'uri' key is required in the activation callback";
            } elseif (! filter_var($value['uri'], FILTER_VALIDATE_URL)) {
                $errors[] = 'Invalid URI in activation callback';
            }
        }
        return $errors;
    }

    protected function setData(array $data): void
    {
        $data = $data['subscriptions'][0] ?? $data;
        $this->data = $data;
    }
}
