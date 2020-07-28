<?php

namespace Platformsh\Client\Model;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Request;
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
        $request = new Request('post', $url, ['Content-Type' => 'application/json'], \GuzzleHttp\json_encode($body));
        $response = $client->send($request);
        $data = \GuzzleHttp\json_decode($response->getBody()->__toString(), true);
        return new self($data);
    }
}
