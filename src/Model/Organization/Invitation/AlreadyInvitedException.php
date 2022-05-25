<?php

namespace Platformsh\Client\Model\Organization\Invitation;

use Platformsh\Client\Model\Organization\Organization;

/**
 * The exception thrown when a user has already been invited to an organization with the same permission(s).
 */
class AlreadyInvitedException extends \RuntimeException
{
    private $email;
    private $organization;
    private $permissions;

    /**
     * @param string $message
     * @param string $email
     * @param Organization $organization
     * @param string[] $permissions
     */
    public function __construct($message, $email, Organization $organization, array $permissions)
    {
        parent::__construct($message);
        $this->email = $email;
        $this->organization = $organization;
        $this->permissions = $permissions;
    }

    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @return Organization
     */
    public function getOrganization()
    {
        return $this->organization;
    }

    /**
     * @return string[]
     */
    public function getPermissions()
    {
        return $this->permissions;
    }
}
