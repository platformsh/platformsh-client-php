<?php

declare(strict_types=1);

namespace Platformsh\Client\Model\Git;

use GuzzleHttp\ClientInterface;
use Platformsh\Client\Exception\GitObjectTypeException;
use Platformsh\Client\Model\ApiResourceBase;
use Platformsh\Client\Model\Project;

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
     */
    public static function fromSha(string $sha, string $baseUrl, ClientInterface $client): false|static
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
    public function getObject(string $path): self|false|Blob|static
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
            return self::fromSha($data['sha'], $this->getUri(), $this->client);
        }

        throw new \RuntimeException('Unrecognised object type: ' . $data['type']);
    }

    /**
     * Get a Blob (file) inside this tree.
     *
     * @return Blob|false
     *   A Blob object, or false if the blob is not found.
     *@throws GitObjectTypeException if the path is a directory.
     */
    public function getBlob(string $path): false|Blob
    {
        $object = $this->getObjectRecursive($path);
        if ($object === false) {
            return false;
        }
        if ($object instanceof self) {
            throw new GitObjectTypeException('The requested file is a directory', $path);
        }

        return $object;
    }

    /**
     * Get a Tree (directory) inside this tree.
     *
     * @return Tree|false
     *   A Tree object or false if the tree is not found.
     *@throws GitObjectTypeException if the path is not a directory.
     */
    public function getTree(string $path): self|false
    {
        $object = $this->getObjectRecursive($path);
        if ($object === false) {
            return false;
        }
        if ($object instanceof self) {
            return $object;
        }
        throw new GitObjectTypeException('Not a directory', $path);
    }

    /**
     * Find an object definition by its path.
     */
    private function getObjectData(string $path): false|array
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
     */
    private function getObjectRecursive(string $path): self|false|Blob
    {
        $tree = $object = $this;
        foreach ($this->splitPath($path) as $part) {
            $object = $tree->getObject($part);
            if (! $object instanceof self) {
                return $object;
            }
            $tree = $object;
        }

        return $object;
    }

    /**
     * Split a tree path into parts.
     *
     * @return string[]
     */
    private function splitPath(string $path): array
    {
        $path = trim(str_replace('\\', '/', $path), '/');

        return explode('/', $path);
    }
}
