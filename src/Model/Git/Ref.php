<?php

namespace Platformsh\Client\Model\Git;

use GuzzleHttp\ClientInterface;
use Platformsh\Client\Model\Project;
use Platformsh\Client\Model\ApiResourceBase;

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
     *
     * @param string          $refName
     * @param Project         $project
     * @param ClientInterface $client
     *
     * @return static|false
     */
    public static function fromName($refName, Project $project, ClientInterface $client)
    {
        $url = $project->getUri() . '/git/refs';

        return static::get($refName, $url, $client);
    }

    /**
     * Get the commit for this ref.
     *
     * @return Commit|false
     */
    public function getCommit()
    {
        $data = $this->object;
        if ($data['type'] !== 'commit') {
            throw new \RuntimeException('This ref is not a commit');
        }
        $url = Project::getProjectBaseFromUrl($this->getUri()) . '/git/commits';

        return Commit::get($data['sha'], $url, $this->client);
    }
}
