<?php

namespace Platformsh\Client\Model\Filter;

/**
 * A filter between two values.
 */
class BetweenFilter extends Filter implements FilterInterface
{
    /**
     * @param string $name
     * @param string|int|float $value1
     * @param string|int|float $value2
     */
    public function __construct($name, $value1, $value2)
    {
        parent::__construct($name, implode(',', [$value1, $value2]), FilterInterface::OP_BETWEEN);
    }
}
