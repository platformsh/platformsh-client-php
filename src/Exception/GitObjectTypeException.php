<?php

namespace Platformsh\Client\Exception;

/**
 * An exception thrown when an expected tree object is a blob, and vice-versa.
 */
class GitObjectTypeException extends \RuntimeException
{
    private $path;

    /**
     * @param string $message
     * @param string $path
     */
    public function __construct($message, $path)
    {
        parent::__construct($message);
        $this->path = $path;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }
}
