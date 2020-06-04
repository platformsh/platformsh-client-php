<?php

namespace Platformsh\Client\Model\Activities;

use Platformsh\Client\Model\Activity;

/**
 * An interface for a resource that has activities associated with it.
 *
 * @see \Platformsh\Client\Model\Resource
 * @see HasActivitiesTrait
 */
interface HasActivitiesInterface {

    /**
     * Get a single activity.
     *
     * @param string $id
     *
     * @return Activity|false
     */
    public function getActivity($id);

    /**
     * Get a list of activities.
     *
     * @param int         $limit
     *   Limit the number of activities to return. Zero for no limit.
     * @param string|null $type
     *   Filter activities by type.
     * @param int|null    $startsAt
     *   A UNIX timestamp for the maximum created date of activities to return.
     * @param string|string[]|null $state
     *   Filter activities by state ("pending", "in_progress", "complete").
     * @param string|string[]|null $result
     *   Filter activities by result ("success" or "failure").
     *
     * @return Activity[]
     */
    public function getActivities($limit = 0, $type = null, $startsAt = null, $state = null, $result = null);
}
