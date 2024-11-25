<?php

declare(strict_types=1);

namespace Platformsh\Client\Exception;

use Platformsh\Client\Model\Environment;

class EnvironmentStateException extends \RuntimeException
{
    protected Environment $environment;

    public function __construct($message, Environment $environment)
    {
        $this->environment = $environment;
        parent::__construct($message);
    }

    public function getEnvironment(): Environment
    {
        return $this->environment;
    }
}
