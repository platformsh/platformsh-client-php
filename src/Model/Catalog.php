<?php

namespace Platformsh\Client\Model;

use GuzzleHttp\ClientInterface;

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
        $response = $client->post($url, ['json' => $data]);
        $items = [];
        foreach ($response->json() as $item) {
            $items[] = CatalogItem::fromData($item);
        }
        return $items;
    }
}
