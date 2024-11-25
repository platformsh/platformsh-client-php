<?php

declare(strict_types=1);

namespace Platformsh\Client\Model\Git;

use GuzzleHttp\ClientInterface;
use Platformsh\Client\Model\ApiResourceBase;
use Platformsh\Client\Model\Project;

/**
 * Git ref resource.
 *
 * @property-read string $id
 *   The ID of this ref.
 * @property-read string $ref
 *   The fully qualified ref name.
 * @property-read array  $object
 *   An object containing 'type' and 'sha'.
 */
class Ref extends ApiResourceBase
{
    /**
     * Get a Ref object in a project.
     */
    public static function fromName(string $refName, Project $project, ClientInterface $client): false|static
    {
        $url = $project->getUri() . '/git/refs';

        return static::get($refName, $url, $client);
    }

    /**
     * Get the commit for this ref.
     */
    public function getCommit(): false|Commit
    {
        $data = $this->object;
        if ($data['type'] !== 'commit') {
            throw new \RuntimeException('This ref is not a commit');
        }
        $url = Project::getProjectBaseFromUrl($this->getUri()) . '/git/commits';

        return Commit::get($data['sha'], $url, $this->client);
    }
}
