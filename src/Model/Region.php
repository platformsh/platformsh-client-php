<?php

namespace Platformsh\Client\Model;

use GuzzleHttp\ClientInterface;

/**
 * Represents a Platform.sh region.
 *
 * @property-read string $id
 * @property-read string $label
 * @property-read bool   $available
 * @property-read bool   $private
 * @property-read string $zone
 * @property-read string $provider
 * @property-read string $endpoint
 */
class Region extends ApiResourceBase
{
    /**
     * @inheritdoc
     */
    protected function setData(array $data)
    {
        $data = isset($data['regions'][0]) ? $data['regions'][0] : $data;
        $data['available'] = !empty($data['available']);
        $data['private'] = !empty($data['private']);
        $this->data = $data;
    }

    /**
     * @inheritdoc
     */
    public static function wrapCollection(array $data, $baseUrl, ClientInterface $client)
    {
        $data = isset($data['regions']) ? $data['regions'] : [];

        return parent::wrapCollection($data, $baseUrl, $client);
    }

    /**
     * @inheritdoc
     */
    public function operationAvailable($op, $refreshDuringCheck = false)
    {
        if ($op === 'edit') {
            return true;
        }

        return parent::operationAvailable($op, $refreshDuringCheck);
    }

    /**
     * @inheritdoc
     */
    public function getLink($rel, $absolute = false)
    {
        if ($rel === '#edit') {
            return $this->getUri($absolute);
        }

        return parent::getLink($rel, $absolute);
    }
}
