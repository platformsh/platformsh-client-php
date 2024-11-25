<?php

declare(strict_types=1);

namespace Platformsh\Client\Model\Filter;

/**
 * A basic filter for a single value.
 */
class Filter implements FilterInterface
{
    private string $name;

    private string $operator;

    private string|int|float $value;

    /**
     * @param string $name
     *   The filter name.
     * @param float|int|string $value
     *   The filter value. Multiple values should be joined together in a
     *   string separated by commas.
     * @param string $operator
     *   One of the FilterInterface::OP_ constants.
     */
    public function __construct(string $name, float|int|string $value, string $operator = FilterInterface::OP_EQUAL)
    {
        $this->name = $name;
        $this->value = $value;
        $this->operator = $operator;
    }

    final public function params(): array
    {
        return [
            \sprintf('filter[%s][%s]', $this->name, $this->operator) => $this->value,
        ];
    }
}
