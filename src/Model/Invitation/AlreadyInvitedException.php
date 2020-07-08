<?php

namespace Platformsh\Client\Model\Invitation;

use Platformsh\Client\Model\Project;

class AlreadyInvitedException extends \RuntimeException{
    private $email;
    private $project;
    private $role;
    private $environments;

    /**
     * @param string $message
     * @param string $email
     * @param Project $project
     * @param string $role
     * @param Environment[] $environments
     */
    public function __construct($message, $email, Project $project, $role, array $environments)
    {
        parent::__construct($message);
        $this->email = $email;
        $this->project = $project;
        $this->role = $role;
        $this->environments = $environments;
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
}
