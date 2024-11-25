<?php

declare(strict_types=1);

namespace Platformsh\Client\Model\Organization\Invitation;

use Platformsh\Client\Model\Organization\Organization;

/**
 * The exception thrown when a user has already been invited to an organization with the same permission(s).
 */
class AlreadyInvitedException extends \RuntimeException
{
    private string $email;

    private Organization $organization;

    private array $permissions;

    /**
     * @param string $email
     * @param string[] $permissions
     */
    public function __construct(string $message, $email, Organization $organization, array $permissions)
    {
        parent::__construct($message);
        $this->email = $email;
        $this->organization = $organization;
        $this->permissions = $permissions;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    /**
     * @return string[]
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }
}
