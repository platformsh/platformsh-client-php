<?php

namespace Platformsh\Client\Model;

use Platformsh\Client\Model\Backups\RestoreOptions;

/**
 * An environment backup.
 *
 * @property-read string $id          The backup name/ID.
 * @property-read string $status      The status of the backup.
 * @property-read int    $index       The index of this automated backup.
 * @property-read string $commit_id   The code commit ID attached to this backup.
 * @property-read string $environment The environment the backup belongs to.
 * @property-read bool   $restorable   Whether the backup is restorable.
 * @property-read string $created_at
 * @property-read string $updated_at
 * @property-read string $expires_at
 */
class Backup extends Resource
{
    const STATUS_CREATED = 'CREATED';
    const STATUS_DELETED = 'DELETED';

    /**
     * Restores a backup.
     *
     * @param RestoreOptions|null $options
     *
     * @return Result
     */
    public function restore(RestoreOptions $options = null)
    {
        return $this->runOperation('restore', 'POST', $options ? $options->toArray() : []);
    }
}
