<?php

namespace Platformsh\Client\Model\Filter;

interface FilterInterface
{
    const OP_EQUAL = 'eq';
    const OP_NOT_EQUAL = 'ne';
    const OP_GREATER_THAN = 'gt';
    const OP_LESS_THAN = 'lt';
    const OP_GREATER_THAN_OR_EQUAL = 'gte';
    const OP_LESS_THAN_OR_EQUAL = 'lte';
    const OP_CONTAINS = 'contains';
    const OP_STARTS_WITH = 'starts';
    const OP_ENDS_WITH = 'ends';
    const OP_IN = 'in';
    const OP_NOT_IN = 'nin';
    const OP_BETWEEN = 'between';

    /**
     * Returns query parameter(s) for the filter.
     *
     * @return array
     */
    public function params();
}
