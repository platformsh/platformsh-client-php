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
     * @param int    $limit
     *   Limit the number of activities to return.
     * @param string $type
     *   Filter activities by type.
     * @param int    $startsAt
     *   A UNIX timestamp for the maximum created date of activities to return.
     *
     * @return Activity[]
     */
    public function getActivities($limit = 0, $type = null, $startsAt = null);
}
