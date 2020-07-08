<?php

namespace Platformsh\Client\Model\Invitation;

class Environment
{
    private $id;
    private $role;

    public function __construct($id, $role)
    {
        $this->id = $id;
        $this->role = $role;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getRole()
    {
        return $this->role;
    }

    /**
     * Returns an array as expected by the invitations API.
     *
     * @param self[] $environments
     *
     * @return array
     */
    public static function listForApi(array $environments)
    {
        $maps = [];
        foreach ($environments as $environment) {
            $maps[] = ['id' => $environment->id, 'role' => $environment->role];
        }
        return $maps;
    }
}
