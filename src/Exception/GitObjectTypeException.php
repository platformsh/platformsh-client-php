<?php

declare(strict_types=1);

namespace Platformsh\Client\Exception;

/**
 * An exception thrown when an expected tree object is a blob, and vice-versa.
 */
class GitObjectTypeException extends \RuntimeException
{
    private string $path;

    /**
     * @param string $path
     */
    public function __construct(string $message, $path)
    {
        parent::__construct($message);
        $this->path = $path;
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
