<?php

namespace Platformsh\Client\Model;

use GuzzleHttp\ClientInterface;
use Platformsh\Client\DataStructure\ReadOnlyStructureTrait;

/**
 * Represents Platform.sh setup options api requests.
 *
 * @property-read array $defaults
 * @property-read string[] $plans
 * @property-read string[] $regions
 */
class SetupOptions
{
    use ReadOnlyStructureTrait;

    /**
     * Creates a setup options list.
     *
     * @param array           $body           array of the data to send in the post.
     * @param string          $url           The URL of the setup options api.
     * @param ClientInterface $client        A suitably configured Guzzle
     *                                       client.
     * @return self
     */
    public static function create(array $body, $url, ClientInterface $client)
    {
        $response = $client->post($url, ['json' => $body]);
        return new self($response->json());
    }
}
