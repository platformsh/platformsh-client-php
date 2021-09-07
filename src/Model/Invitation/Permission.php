<?php

namespace Platformsh\Client\Model\Invitation;

/**
 * Represents an item in the "permissions" list for a project invitation.
 */
class Permission
{
    private $type;
    private $role;

    /**
     * Constructor.
     *
     * @param string $type
     * @param string $role
     */
    public function __construct($type, $role)
    {
        $this->type = $type;
        $this->role = $role;
    }

    /**
     * Returns an array as expected by the invitations API.
     *
     * @param self[] $permissions
     *
     * @return array
     */
    public static function listForApi(array $permissions)
    {
        $maps = [];
        foreach ($permissions as $item) {
            $maps[] = ['type' => $item->type, 'role' => $item->role];
        }
        return $maps;
    }
}
