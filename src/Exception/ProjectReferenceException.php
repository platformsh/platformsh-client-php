<?php

namespace Platformsh\Client\Exception;

/**
 * An exception thrown when a project reference cannot be resolved.
 */
class ProjectReferenceException extends \RuntimeException
{
    protected $projectId;

    /**
     * @param string $projectId
     * @param string|null $message
     * @param \Exception|null $previous
     */
    public function __construct($projectId, $message = null, \Exception $previous = null)
    {
        $this->projectId = $projectId;
        $message = $message ?: 'Cannot resolve reference for project: ' . $projectId;
        parent::__construct($message, 0, $previous);
    }

    public function getProjectId()
    {
        return $this->projectId;
    }
}
