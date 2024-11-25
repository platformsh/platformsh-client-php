<?php

declare(strict_types=1);

namespace Platformsh\Client\Model\Invitation;

/**
 * Represents an item in the "permissions" list for a project invitation.
 */
class Permission
{
    private string $type;

    private string $role;

    public function __construct(string $type, string $role)
    {
        $this->type = $type;
        $this->role = $role;
    }

    /**
     * Returns an array as expected by the invitations API.
     *
     * @param self[] $permissions
     */
    public static function listForApi(array $permissions): array
    {
        $maps = [];
        foreach ($permissions as $item) {
            $maps[] = [
                'type' => $item->type,
                'role' => $item->role,
            ];
        }
        return $maps;
    }
}
