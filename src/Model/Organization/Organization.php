<?php

namespace Platformsh\Client\Model\Organization;

use GuzzleHttp\ClientInterface;
use Platformsh\Client\Model\Ref\UserRef;
use Platformsh\Client\Model\ResourceWithReferences;

/**
 * @property-read string $id
 * @property-read string $owner_id
 * @property-read string $namespace
 * @property-read string $name
 * @property-read string $label
 * @property-read string $created_at
 * @property-read string $updated_at
 */
class Organization extends ResourceWithReferences
{
    /**
     * Returns a list of the organization's members.
     *
     * @return Member[]
     */
    public function getMembers()
    {
        return Member::getCollection($this->getLink('members'), 0, [], $this->client);
    }

    /**
     * Returns detailed information about the organization's owner, if known.
     *
     * @return UserRef|null
     */
    public function getOwnerInfo()
    {
        if (isset($this->data['owner_id']) && isset($this->data['ref:users'][$this->data['owner_id']])) {
            return $this->data['ref:users'][$this->data['owner_id']];
        }
        return null;
    }

    /**
     * {@inheritdoc}
     *
     * @internal Use PlatformClient::createOrganization() to create an organization.
     *
     * @see \Platformsh\Client\PlatformClient::createOrganization()
     *
     * @return static
     */
    public static function create(array $body, $collectionUrl, ClientInterface $client)
    {
        $result = parent::create($body, $collectionUrl, $client);
        return new static($result->getData(), $collectionUrl, $client);
    }
}
