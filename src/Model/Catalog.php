<?php

namespace Platformsh\Client\Model;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Utils;

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
        $data = Utils::jsonDecode($response->getBody(), true);
        $items = [];
        foreach ($data as $item) {
            $items[] = CatalogItem::fromData($item);
        }
        return $items;
    }
}
