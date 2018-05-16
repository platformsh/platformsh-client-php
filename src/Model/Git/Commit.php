<?php

namespace Platformsh\Client\Model\Git;

use Platformsh\Client\Model\ApiResourceBase;

/**
 * Git commit resource.
 *
 * @property-read string $id
 * @property-read string $sha
 * @property-read string $tree
 * @property-read string $message
 * @property-read array  $author
 * @property-read array  $committer
 * @property-read array  $parents
 */
class Commit extends ApiResourceBase
{
    /**
     * Get the root Tree for this commit.
     *
     * @return Tree|false
     */
    public function getTree()
    {
        return Tree::fromSha($this->tree, $this->getUri(), $this->client);
    }
}
