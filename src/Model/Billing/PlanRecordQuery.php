<?php

namespace Platformsh\Client\Model\Billing;

class PlanRecordQuery
{
    private $filters = [];

    /**
     * Restrict the query to a date/time period.
     *
     * @param \DateTime|null $start
     * @param \DateTime|null $end
     */
    public function setPeriod(\DateTime $start = null, \DateTime $end = null)
    {
        $this->filters['start'] = $start !== null? $start->format('c') : null;
        $this->filters['end'] = $end !== null ? $end->format('c') : null;
    }

    /**
     * Restrict the query to an owner's ID.
     *
     * @param array|string|null $owner
     */
    public function setOwner($owner)
    {
        $this->filters['owner'] = $owner;
    }

    /**
     * Restrict the query to a plan type, e.g. 'development', 'medium', etc.
     *
     * @param array|string|null $plan
     */
    public function setPlan($plan)
    {
        $this->filters['plan'] = $plan;
    }

    /**
     * Get the URL query parameters.
     *
     * @return array
     */
    public function getParams()
    {
        $filters = array_filter($this->filters, function ($value) {
            return $value !== null;
        });

        $filters = array_map(function ($value) {
            return is_array($value) ? ['value' => $value, 'operator' => 'IN'] : $value;
        }, $filters);

        return count($filters) ? ['filter' => $filters] : [];
    }
}
