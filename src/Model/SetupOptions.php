<?php

declare(strict_types=1);

namespace Platformsh\Client\Model;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Utils;
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
     * @param string $url           The URL of the setup options api.
     * @param ClientInterface $client        A suitably configured Guzzle
     *                                       client.
     */
    public static function create(array $body, string $url, ClientInterface $client): self
    {
        $request = new Request('post', $url, [
            'Content-Type' => 'application/json',
        ], Utils::jsonEncode($body));
        $response = $client->send($request);
        $data = Utils::jsonDecode((string) $response->getBody(), true);
        return new self($data);
    }

    /**
     * Fetches a setup options list from a known URL.
     */
    public static function get(string $url, ClientInterface $client): self
    {
        $response = $client->get($url);
        $data = Utils::jsonDecode((string) $response->getBody(), true);
        return new self($data);
    }
}
