<?php

declare(strict_types=1);

namespace Platformsh\Client\Model\Invitation;

use Platformsh\Client\Model\Project;

/**
 * The exception thrown when a user has already been invited to a project with the same role and environment(s).
 */
class AlreadyInvitedException extends \RuntimeException
{
    private string $email;

    private Project $project;

    private string $role;

    private array $environments;

    private array $permissions;

    /**
     * @param string $email
     * @param Environment[] $environments
     * @param Permission[] $permissions
     */
    public function __construct(string $message, $email, Project $project, string $role, array $environments, array $permissions)
    {
        parent::__construct($message);
        $this->email = $email;
        $this->project = $project;
        $this->role = $role;
        $this->environments = $environments;
        $this->permissions = $permissions;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    /**
     * @return Environment[]
     */
    public function getEnvironments(): array
    {
        return $this->environments;
    }

    /**
     * @return Permission[]
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }
}
