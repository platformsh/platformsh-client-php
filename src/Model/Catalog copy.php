<?php

namespace Platformsh\Client\Model;

use GuzzleHttp\ClientInterface;

/**
 * Represents a Platform.sh catalog.
 *
 * @property-read int    $id
 * @property-read string $label
 * @property-read bool   $available
 * @property-read bool   $private
 * @property-read string $zone
 * @property-read string $endpoint
 */
class Catalog extends Resource
{
    /**
     * @inheritdoc
     */
    protected function setData(array $data)
    {
        $data = isset($data['info']) ? $data['info'] : $data;
        $this->data = $data;
    }

    /**
     * @inheritdoc
     */
    public static function wrapCollection(array $data, $baseUrl, ClientInterface $client)
    {
        $data = isset($data['info']) ? $data['info'] : [];

        return parent::wrapCollection($data, $baseUrl, $client);
    }
}
