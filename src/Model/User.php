<?php

namespace Platformsh\Client\Model;

class User extends Resource
{

    /**
     * @return SshKey[]
     */
    public function getSshKeys()
    {
        return $this->client->get($this->getLink('ssh-keys'));
    }
}
