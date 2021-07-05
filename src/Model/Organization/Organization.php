<?php

namespace Platformsh\Client\Model\Organization;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use Platformsh\Client\Model\Ref\Resolver;
use Platformsh\Client\Model\Ref\UserRef;
use Platformsh\Client\Model\Resource;

/**
 * @property-read string $id
 * @property-read string $owner_id
 * @property-read string $namespace
 * @property-read string $name
 * @property-read string $label
 * @property-read string $created_at
 * @property-read string $updated_at
 */
class Organization extends Resource
{
    protected function setData(array $data)
    {
        // References are resolved upon initialization so that the links are less likely to have expired.
        $data = self::resolveReferences(new Resolver($this->client, $this->baseUrl), $data);
        parent::setData($data);
    }

    /**
     * @param Resolver $resolver
     * @param array $data
     *
     * @return array
     */
    private static function resolveReferences(Resolver $resolver, array $data)
    {
        if (isset($data['_links'])) {
            try {
                $data = $resolver->resolveReferences($data);
            } catch (\Exception $e) {
                $message = $e->getMessage();
                if ($e instanceof BadResponseException && $e->getResponse()) {
                    $message = \sprintf('status code %d', $e->getResponse()->getStatusCode());
                }
                \trigger_error('Unable to resolve references: ' . $message, E_USER_WARNING);
            }
        }
        return $data;
    }

    public static function wrapCollection(array $data, $baseUrl, ClientInterface $client)
    {
        $data = self::resolveReferences(new Resolver($client, $baseUrl), $data);

        $resources = [];
        foreach ($data['items'] as $item) {
            // Add the owner user reference onto the individual item (the rest of $data is discarded).
            if (isset($item['owner_id']) && isset($data['ref:users'][$item['owner_id']])) {
                $item['ref:users'][$item['owner_id']] = $data['ref:users'][$item['owner_id']];
            }
            $resources[] = new static($item, $baseUrl, $client);
        }

        return $resources;
    }

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
