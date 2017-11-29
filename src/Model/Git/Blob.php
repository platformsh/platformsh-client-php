<?php

namespace Platformsh\Client\Model\Git;

use GuzzleHttp\ClientInterface;
use Platformsh\Client\Model\Project;
use Platformsh\Client\Model\Resource;
use sskaje\converter\base\Base;

/**
 * Git blob resource.
 *
 * @property-read string $sha
 * @property-read string $size
 * @property-read string $encoding
 * @property-read string $content
 */
class Blob extends Resource
{
    /**
     * Get the Blob object for an SHA hash.
     *
     * @param string          $sha
     * @param string          $baseUrl
     * @param ClientInterface $client
     *
     * @return static|false
     */
    public static function fromSha($sha, $baseUrl, ClientInterface $client)
    {
        $url = Project::getProjectBaseFromUrl($baseUrl) . '/git/blobs';

        return static::get($sha, $url, $client);
    }

    /**
     * Get the raw content of the file.
     *
     * @return string
     */
    public function getRawContent()
    {
        if ($this->size == 0) {
            return '';
        }

        if ($this->encoding === 'base64') {
            // PHP's built-in base64_decode() function does not work for
            // binary content encoded according to RFC 4648. The
            // sskaje/base-converter library is used instead.
            $base64 = new Base();
            $raw = $base64->decode('base64.MSB', $this->content);
            if ($raw === false) {
                throw new \RuntimeException('Failed to decode content');
            }

            return $raw;
        }

        throw new \RuntimeException('Unrecognised blob encoding: ' . $this->encoding);
    }
}
