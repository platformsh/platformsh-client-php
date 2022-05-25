<?php

namespace Platformsh\Client\Model\Filter;

/**
 * A filter that works with a list of strings.
 */
class ListFilter extends Filter implements FilterInterface
{
    /**
     * @param string $name
     *   The filter name.
     * @param array $values
     *   The filter values.
     * @param bool $in
     *   True for "IN", false for "NOT IN".
     */
    public function __construct($name, array $values, $in = true)
    {
        parent::__construct($name, \implode(',', $values), $in ? FilterInterface::OP_IN : FilterInterface::OP_NOT_IN);
    }
}
