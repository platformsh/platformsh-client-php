<?php

declare(strict_types=1);

namespace Platformsh\Client\Model\Invitation;

/**
 * Represents an item in the "environments" list for a project invitation.
 */
class Environment
{
    private string $id;

    private string $role;

    public function __construct($id, $role)
    {
        $this->id = $id;
        $this->role = $role;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    /**
     * Returns an array as expected by the invitations API.
     *
     * @param self[] $environments
     */
    public static function listForApi(array $environments): array
    {
        $maps = [];
        foreach ($environments as $environment) {
            $maps[] = [
                'id' => $environment->id,
                'role' => $environment->role,
            ];
        }
        return $maps;
    }
}
