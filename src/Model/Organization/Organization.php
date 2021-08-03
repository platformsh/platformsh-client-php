<?php

namespace Platformsh\Client\Model\Organization;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use Platformsh\Client\Model\Organization\Invitation\AlreadyInvitedException;
use Platformsh\Client\Model\Organization\Invitation\OrganizationInvitation;
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
    public function getLink($rel, $absolute = true)
    {
        if ($rel === 'invitations') {
            return $this->getUri($absolute) . '/invitations';
        }
        return parent::getLink($rel, $absolute);
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
}
