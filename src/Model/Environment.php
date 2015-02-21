<?php

namespace Platformsh\Client\Model;

class Environment extends Resource
{

    /**
     * @return EnvironmentActivity[]
     */
    public function getActivities()
    {
        return $this->get($this->getLink('activities'));
    }
}
