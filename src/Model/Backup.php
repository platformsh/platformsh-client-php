<?php

namespace Platformsh\Client\Model;

use GuzzleHttp\ClientInterface;
use function GuzzleHttp\Psr7\uri_for;

/**
 * An environment backup.
 *
 * @property-read string $id          The backup name/ID.
 * @property-read string $status      The status of the backup.
 * @property-read int    $index       The index of this automated backup.
 * @property-read string $commit_id   The code commit ID attached to this
 *                backup.
 * @property-read string $environment The environment the backup belongs to.
 * @property-read string $created_at
 * @property-read string $updated_at
 * @property-read string $expires_at
 */
class Backup extends ApiResourceBase
{
    const STATUS_CREATED = 'CREATED';
    const STATUS_DELETED = 'DELETED';

    /**
     * Restore this backup.
     *
     * @param string|null $environment The environment ID to restore to
     *                                 (defaults to the backup's current
     *                                 environment).
     * @param string      $branchFrom  If a new environment is going to be
     *                                 created via this action, specify the
     *                                 parent branch for the new environment
     *                                 (defaults to 'master').
     *
     * @return \Platformsh\Client\Model\Result
     */
    public function restore($environment = null, $branchFrom = null)
    {
        $options = [];
        if ($environment !== null) {
            $options['environment_name'] = $environment;
        }
        if ($branchFrom !== null) {
            $options['branch_from'] = $branchFrom;
        }

        return $this->runOperation('restore', 'post', $options);
    }
}
