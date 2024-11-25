<?php

declare(strict_types=1);

namespace Platformsh\Client\Model\Billing;

class PlanRecordQuery
{
    private array $filters = [];

    /**
     * Restrict the query to a date/time period.
     */
    public function setPeriod(\DateTime $start = null, \DateTime $end = null): void
    {
        $this->filters['start'] = $start?->format('c');
        $this->filters['end'] = $end?->format('c');
    }

    /**
     * Restrict the query to an owner's ID.
     */
    public function setOwner(array|string|null $owner): void
    {
        $this->filters['owner'] = $owner;
    }

    /**
     * Restrict the query to a plan type, e.g. 'development', 'medium', etc.
     */
    public function setPlan(array|string|null $plan): void
    {
        $this->filters['plan'] = $plan;
    }

    /**
     * Get the URL query parameters.
     */
    public function getParams(): array
    {
        $filters = array_filter($this->filters, function ($value) {
            return $value !== null;
        });

        $filters = array_map(function ($value) {
            return is_array($value) ? [
                'value' => $value,
                'operator' => 'IN',
            ] : $value;
        }, $filters);

        return count($filters) ? [
            'filter' => $filters,
        ] : [];
    }
}
