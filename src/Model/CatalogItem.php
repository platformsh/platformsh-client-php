<?php

namespace Platformsh\Client\Model;

use Platformsh\Client\DataStructure\ReadOnlyStructureTrait;

/**
 * Represents a Platform.sh catalog item.
 *
 * @property-read string $template
 * @property-read array $info
 * @property-read array $initialize
 * @property-read int $version
 */
class CatalogItem
{
    use ReadOnlyStructureTrait;
}
