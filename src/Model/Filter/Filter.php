<?php

namespace Platformsh\Client\Model\Filter;

/**
 * A basic filter for a single value.
 */
class Filter implements FilterInterface
{
    private $name;
    private $operator;
    private $value;

    /**
     * @param string $name
     *   The filter name.
     * @param string|int|float $value
     *   The filter value. Multiple values should be joined together in a
     *   string separated by commas.
     * @param string $operator
     *   One of the FilterInterface::OP_ constants.
     */
    public function __construct($name, $value, $operator = FilterInterface::OP_EQUAL)
    {
        $this->name = $name;
        $this->value = $value;
        $this->operator = $operator;
    }

    public final function params()
    {
        return [
            \sprintf('filter[%s][%s]', $this->name, $this->operator) => $this->value,
        ];
    }
}
