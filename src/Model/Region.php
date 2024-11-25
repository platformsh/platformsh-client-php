<?php

declare(strict_types=1);

namespace Platformsh\Client\Model;

/**
 * Represents a Platform.sh region.
 *
 * @property-read string $id
 * @property-read string $label
 * @property-read bool   $available
 * @property-read bool   $private
 * @property-read string $zone
 * @property-read array{name: string} $provider
 * @property-read string $endpoint
 */
class Region extends ApiResourceBase
{
    protected static ?string $collectionItemsKey = 'regions';

    public function operationAvailable(string $op, bool $refreshDuringCheck = false): bool
    {
        if ($op === 'edit') {
            return true;
        }

        return parent::operationAvailable($op, $refreshDuringCheck);
    }

    public function getLink(string $rel, bool $absolute = false): string
    {
        if ($rel === '#edit') {
            return $this->getUri($absolute);
        }

        return parent::getLink($rel, $absolute);
    }
}
