<?php

namespace Platformsh\Client\Exception;

use Platformsh\Client\Model\Environment;

class EnvironmentStateException extends \RuntimeException
{
    protected $environment;

    public function __construct($message, Environment $environment)
    {
        $this->environment = $environment;
        parent::__construct($message, null, null);
    }

    public function getEnvironment()
    {
        return $this->environment;
    }
}
