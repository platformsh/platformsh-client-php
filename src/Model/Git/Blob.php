<?php

declare(strict_types=1);

namespace Platformsh\Client\Model\Git;

use GuzzleHttp\ClientInterface;
use Platformsh\Client\Model\ApiResourceBase;
use Platformsh\Client\Model\Project;

/**
 * Git blob resource.
 *
 * @property-read string $sha
 * @property-read string $size
 * @property-read string $encoding
 * @property-read string $content
 */
class Blob extends ApiResourceBase
{
    /**
     * Get the Blob object for an SHA hash.
     */
    public static function fromSha(string $sha, string $baseUrl, ClientInterface $client): false|static
    {
        $url = Project::getProjectBaseFromUrl($baseUrl) . '/git/blobs';

        return static::get($sha, $url, $client);
    }

    /**
     * Get the raw content of the file.
     */
    public function getRawContent(): string
    {
        if ($this->size === 0) {
            return '';
        }

        if ($this->encoding === 'base64') {
            $raw = base64_decode($this->content, true);
            if ($raw === false) {
                throw new \RuntimeException('Failed to decode content');
            }

            return $raw;
        }

        throw new \RuntimeException('Unrecognised blob encoding: ' . $this->encoding);
    }
}
