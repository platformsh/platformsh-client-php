<?php

namespace Platformsh\Client\Model;

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
    protected static $collectionItemsKey = 'regions';

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
