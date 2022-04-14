<?php

namespace Platformsh\Client\Model;

/**
 * Represents a Platform.sh region.
 *
 * @property-read int    $id
 * @property-read string $label
 * @property-read bool   $available
 * @property-read bool   $private
 * @property-read string $zone
 * @property-read string $endpoint
 */
class Region extends Resource
{
    protected static $collectionItemsKey = 'regions';

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
    protected function isOperationAvailable($op)
    {
        if ($op === 'edit') {
            return true;
        }

        return parent::isOperationAvailable($op);
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
