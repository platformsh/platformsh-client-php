<?php

namespace Platformsh\Client\Model\Organization;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use Platformsh\Client\Model\Organization\Invitation\AlreadyInvitedException;
use Platformsh\Client\Model\Organization\Invitation\OrganizationInvitation;
use Platformsh\Client\Model\Ref\UserRef;
use Platformsh\Client\Model\ResourceWithReferences;
use Platformsh\Client\Model\Result;
use Platformsh\Client\Model\SetupOptions;
use Platformsh\Client\Model\Subscription;

/**
 * @property-read string $id The organization ID
 * @property-read string $owner_id The user ID of the organization owner
 * @property-read string $name The organization's machine name (used in URLs)
 * @property-read string $label The organization's "human-readable" name
 * @property-read string $country ISO 2-letter country code
 * @property-read string $namespace
 * @property-read string $vendor
 * @property-read string $created_at
 * @property-read string $updated_at
 */
class Organization extends ResourceWithReferences
{
    public function getLink($rel, $absolute = true)
    {
        // @todo remove this when HAL links are provided in the API
        if (\in_array($rel, ['invitations', 'address', 'profile'])) {
            return $this->getUri($absolute) . '/' . $rel;
        }
        return parent::getLink($rel, $absolute);
    }

    /**
     * Updates the organization.
     *
     * This updates the resource's internal data with the API response.
     *
     * @param array $values
     *
     * @return Result
     */
    public function update(array $values)
    {
        // @todo use getLink('#edit') when it is available
        $url = $this->getUri();
        $options = [];
        if (!empty($values)) {
            $options['json'] = $values;
        }
        $response = $this->client->patch($url, $options);
        $data = $response->json();
        $this->setData($data);

        return new Result($data, $this->baseUrl, $this->client, get_called_class());
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
     * Returns a single organization subscription.
     *
     * @param string $id
     *
     * @return Subscription|false
     */
    public function getSubscription($id)
    {
        return Subscription::get($id, $this->getUri() . '/subscriptions', $this->client);
    }

    /**
     * Returns setup options for the organization.
     *
     * @return SetupOptions
     */
    public function getSetupOptions()
    {
        return SetupOptions::get($this->getUri() . '/setup/options', $this->client);
    }

    /**
     * Returns a list of organization subscriptions.
     *
     * @param array $query
     *   Query parameters to use. The default query is a filter that returns only active and suspended subscriptions.
     *
     * @return Subscription[]
     */
    public function getSubscriptions(array $query = [])
    {
        $options = [];
        if (!empty($query)) {
            $options['query'] = $query;
        } else {
            $options['query']['filter']['status']['value'][] = 'active';
            $options['query']['filter']['status']['value'][] = 'suspended';
            $options['query']['filter']['status']['operator'] = 'IN';
        }
        return Subscription::getCollection($this->getUri() . '/subscriptions', 0, $options, $this->client);
    }

    /**
     * Invites a new user to an organization using their email address.
     *
     * This is only possible after setting the API gateway URL. This will be
     * the case already if the project was instantiated via a PlatformClient
     * method such as PlatformClient::getProject(). Otherwise, use
     * Project::setApiUrl() before calling this method.
     *
     * @see Project::setApiUrl()
     * @see \Platformsh\Client\PlatformClient::getProject()
     *
     * @param string $email
     *   The user's email address.
     * @param string[] $permissions
     *   The user's permissions on the organization.
     * @param bool $force
     *   Whether to re-send the invitation, if an invitation has already been sent to the same email address.
     *
     * @throws AlreadyInvitedException if there is a pending invitation for the same email address
     *
     * @return OrganizationInvitation
     */
    public function inviteMemberByEmail($email, array $permissions = [], $force = false)
    {
        $data = [
            'email' => $email,
            'permissions' => $permissions,
            'force' => $force,
        ];

        $request = $this->client->createRequest('post', $this->getLink('invitations'), ['json' => $data]);
        try {
            $data = self::send($request, $this->client);
        } catch (BadResponseException $e) {
            if ($e->getResponse() && $e->getResponse()->getStatusCode() === 409) {
                throw new AlreadyInvitedException(
                    'An invitation has already been created for this email address and permission(s)',
                    $email,
                    $this,
                    $permissions
                );
            }
            throw $e;
        }

        return new OrganizationInvitation($data, $this->getLink('invitations'), $this->client);
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

    /**
     * Returns the organization address.
     *
     * @return Address
     */
    public function getAddress()
    {
        $url = $this->getLink('address');
        $response = $this->client->get($url);
        return new Address($response->json(), $url, $this->client);
    }

    /**
     * Returns the organization profile.
     *
     * @return Profile
     */
    public function getProfile()
    {
        $url = $this->getLink('profile');
        $response = $this->client->get($url);
        return new Profile($response->json(), $url, $this->client);
    }
}
