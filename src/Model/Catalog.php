<?php

declare(strict_types=1);

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
     * @return CatalogItem[]
     */
    public static function create(array $data, string $url, ClientInterface $client): array
    {
        $request = new Request('post', $url, [
            'Content-Type' => 'application/json',
        ], Utils::jsonEncode($data));
        $response = $client->send($request);
        $data = Utils::jsonDecode($response->getBody()->__toString(), true);
        $items = [];
        foreach ($data as $item) {
            $items[] = CatalogItem::fromData($item);
        }
        return $items;
    }
}
