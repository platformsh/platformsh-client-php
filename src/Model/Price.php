<?php

namespace Platformsh\Client\Model;

use Platformsh\Client\DataStructure\ReadOnlyStructureTrait;

/**
 * Represents a price.
 *
 * @property-read string    $formatted
 * @property-read int|float $amount
 * @property-read string    $currency_code
 */
class Price
{
    use ReadOnlyStructureTrait;

    /**
     * Formats a price as a string.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->formatted;
    }
}
