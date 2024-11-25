<?php

declare(strict_types=1);

namespace Platformsh\Client\Model\Filter;

interface FilterInterface
{
    public const OP_EQUAL = 'eq';

    public const OP_NOT_EQUAL = 'ne';

    public const OP_GREATER_THAN = 'gt';

    public const OP_LESS_THAN = 'lt';

    public const OP_GREATER_THAN_OR_EQUAL = 'gte';

    public const OP_LESS_THAN_OR_EQUAL = 'lte';

    public const OP_CONTAINS = 'contains';

    public const OP_STARTS_WITH = 'starts';

    public const OP_ENDS_WITH = 'ends';

    public const OP_IN = 'in';

    public const OP_NOT_IN = 'nin';

    public const OP_BETWEEN = 'between';

    /**
     * Returns query parameter(s) for the filter.
     *
     * @return array
     */
    public function params();
}
