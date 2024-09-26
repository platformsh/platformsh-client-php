<?php

namespace Platformsh\Client\Model\Ref;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Url;

class Resolver
{
    private $client;
    private $baseUrl;

    /**
     * Resolver constructor.
     *
     * @param ClientInterface $client An authenticated Guzzle HTTP client.
     * @param string $baseUrl The API base URL (for making URLs absolute).
     */
    public function __construct(ClientInterface $client, $baseUrl)
    {
        $this->client = $client;
        $this->baseUrl = $baseUrl;
    }

    /**
     * Resolves HAL reference links in resource data.
     *
     * @param array $data
     *   Data from a resource or collection, containing HAL links.
     *
     * @return array
     *   The $data modified.
     */
    public function resolveReferences(array $data)
    {
        if (!isset($data['_links'])) {
            return $data;
        }
        foreach ($data['_links'] as $key => $link) {
            if (\strpos($key, 'ref:') === 0 && ($parts = \explode(':', $key, 3)) && \count($parts) === 3) {
                $set = $parts[1];
                $absoluteUrl = Url::fromString($this->baseUrl)->combine($link['href']);
                if (!isset($data['ref:' . $set])) {
                    $data['ref:' . $set] = [];
                }
                $data['ref:' . $set] += $this->client->get($absoluteUrl)->json();
                unset($data['_links'][$key]);
            }
        }
        // Transform arrays into objects.
        if (isset($data['ref:users'])) {
            foreach ($data['ref:users'] as &$item) {
                if ($item !== null && !$item instanceof UserRef) {
                    $item = UserRef::fromData($item);
                }
            }
        }
        if (isset($data['ref:organizations'])) {
            foreach ($data['ref:organizations'] as &$item) {
                if ($item !== null && !$item instanceof OrganizationRef) {
                    $item = OrganizationRef::fromData($item);
                }
            }
        }
        if (isset($data['ref:projects'])) {
            foreach ($data['ref:projects'] as &$item) {
                if ($item !== null && !$item instanceof ProjectRef) {
                    $item = ProjectRef::fromData($item);
                }
            }
        }
        if (isset($data['ref:teams'])) {
            foreach ($data['ref:teams'] as &$item) {
                if ($item !== null && !$item instanceof TeamRef) {
                    $item = TeamRef::fromData($item);
                }
            }
        }
        return $data;
    }
}
