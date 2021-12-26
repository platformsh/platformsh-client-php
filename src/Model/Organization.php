<?php

namespace Platformsh\Client\Model;

use Platformsh\Client\Model\Subscription\SubscriptionOptions;

/**
 * Represents a Platform.sh organization.
 *
 * @property-read string $id
 * @property-read string $owner_id
 * @property-read string $namespace
 * @property-read string $name
 * @property-read string $label
 * @property-read string $created_at
 * @property-read string $updated_at
 */
class Organization extends ApiResourceBase
{

    /**
     * Get members of the organization.
     *
     * @return OrganizationAccess[]
     */
    public function getMembers()
    {
        return OrganizationAccess::getCollection($this->getLink('members'), 0, [], $this->client);
    }

    /**
     * Get subscriptions belonging to the organization.
     *
     * @return Subscription[]
     */
    public function getSubscriptions()
    {
        return Subscription::getCollection($this->getLink('subscriptions'), 0, [], $this->client);
    }

    public function createSubscription(SubscriptionOptions $options) {
        $values = $options->toArray();
        return Subscription::create($values, $this->getLink('create-subscription'), $this->client);
    }

}
