<?php

namespace Platformsh\Client\Model\Invitation;

use Platformsh\Client\Model\Project;

/**
 * The exception thrown when a user has already been invited to a project with the same role and environment(s).
 */
class AlreadyInvitedException extends \RuntimeException{
    private $email;
    private $project;
    private $role;
    private $environments;
    private $permissions;

    /**
     * @param string $message
     * @param string $email
     * @param Project $project
     * @param string $role
     * @param Environment[] $environments
     * @param Permission[] $permissions
     */
    public function __construct($message, $email, Project $project, $role, array $environments, array $permissions)
    {
        parent::__construct($message);
        $this->email = $email;
        $this->project = $project;
        $this->role = $role;
        $this->environments = $environments;
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
     * @return Project
     */
    public function getProject()
    {
        return $this->project;
    }

    /**
     * @return string
     */
    public function getRole()
    {
        return $this->role;
    }

    /**
     * @return Environment[]
     */
    public function getEnvironments()
    {
        return $this->environments;
    }

    /**
     * @return Permission[]
     */
    public function getPermissions()
    {
        return $this->permissions;
    }
}
