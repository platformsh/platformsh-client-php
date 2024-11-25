<?php

declare(strict_types=1);

namespace Platformsh\Client\Exception;

/**
 * An exception thrown when a project reference cannot be resolved.
 */
class ProjectReferenceException extends \RuntimeException
{
    protected string $projectId;

    /**
     * @param string|null $message
     */
    public function __construct(string $projectId, $message = null, \Exception $previous = null)
    {
        $this->projectId = $projectId;
        $message = $message ?: 'Cannot resolve reference for project: ' . $projectId;
        parent::__construct($message, 0, $previous);
    }

    public function getProjectId(): string
    {
        return $this->projectId;
    }
}
