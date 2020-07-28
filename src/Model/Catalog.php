<?php

namespace Platformsh\Client\Model;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Request;

/**
 * Represents a Platform.sh catalog.
 */
class Catalog
{
    /**
     * Creates a catalog.
     *
     * @param array $data
     * @param string $url
     * @param ClientInterface $client
     *
     * @return CatalogItem[]
     */
    public static function create(array $data, $url, ClientInterface $client)
    {
        $request = new Request('post', $url, ['Content-Type' => 'application/json'], \GuzzleHttp\json_encode($data));
        $response = $client->send($request);
        $data = \GuzzleHttp\json_decode($response->getBody()->__toString(), true);
        $items = [];
        foreach ($data as $item) {
            $items[] = CatalogItem::fromData($item);
        }
        return $items;
    }
}
