<?php

namespace Platformsh\Client\Model\Git;

use GuzzleHttp\ClientInterface;
use Platformsh\Client\Exception\GitObjectTypeException;
use Platformsh\Client\Model\Project;
use Platformsh\Client\Model\ApiResourceBase;

/**
 * Git tree resource.
 *
 * @property-read string $id
 * @property-read string $sha
 * @property-read array  $tree
 */
class Tree extends ApiResourceBase
{
    /**
     * Get the Tree object for an SHA hash.
     *
     * @param string          $sha
     * @param string          $baseUrl
     * @param ClientInterface $client
     *
     * @return static|false
     */
    public static function fromSha($sha, $baseUrl, ClientInterface $client)
    {
        $url = Project::getProjectBaseFromUrl($baseUrl) . '/git/trees';

        return static::get($sha, $url, $client);
    }

    /**
     * Get an object in this tree.
     *
     * @param string $path The path to an object in the tree.
     *
     * @return Blob|Tree|false
     *   A Blob or Tree object, or false if the object does not exist.
     */
    public function getObject($path)
    {
        if ($path === '' || $path === '.') {
            return $this;
        }
        $data = $this->getObjectData($path);
        if ($data === false) {
            return false;
        }

        if ($data['type'] === 'blob') {
            return Blob::fromSha($data['sha'], $this->getUri(), $this->client);
        } elseif ($data['type'] === 'tree') {
            return Tree::fromSha($data['sha'], $this->getUri(), $this->client);
        }

        throw new \RuntimeException('Unrecognised object type: ' . $data['type']);
    }

    /**
     * Find an object definition by its path.
     *
     * @param string $path
     *
     * @return array|false
     */
    private function getObjectData($path)
    {
        foreach ($this->tree as $objectData) {
            if ($objectData['path'] === $path) {
                return $objectData;
            }
        }

        return false;
    }

    /**
     * Get an object recursively in this tree.
     *
     * @param string $path
     *
     * @return Blob|Tree|false
     */
    private function getObjectRecursive($path)
    {
        $tree = $object = $this;
        foreach ($this->splitPath($path) as $part) {
            $object = $tree->getObject($part);
            if (!$object instanceof Tree) {
                return $object;
            }
            $tree = $object;
        }

        return $object;
    }

    /**
     * Split a tree path into parts.
     *
     * @param string $path
     *
     * @return string[]
     */
    private function splitPath($path)
    {
        $path = trim(str_replace('\\', '/', $path), '/');

        return explode('/', $path);
    }

    /**
     * Get a Blob (file) inside this tree.
     *
     * @param string $path
     *
     * @throws GitObjectTypeException if the path is a directory.
     *
     * @return Blob|false
     *   A Blob object, or false if the blob is not found.
     */
    public function getBlob($path)
    {
        $object = $this->getObjectRecursive($path);
        if ($object === false) {
            return false;
        }
        if ($object instanceof Blob) {
            return $object;
        }
        if ($object instanceof Tree) {
            throw new GitObjectTypeException('The requested file is a directory', $path);
        }

        return false;
    }

    /**
     * Get a Tree (directory) inside this tree.
     *
     * @param string $path
     *
     * @throws GitObjectTypeException if the path is not a directory.
     *
     * @return Tree|false
     *   A Tree object or false if the tree is not found.
     */
    public function getTree($path)
    {
        $object = $this->getObjectRecursive($path);
        if ($object === false) {
            return false;
        }
        if ($object instanceof Tree) {
            return $object;
        }
        throw new GitObjectTypeException('Not a directory', $path);
    }
}
