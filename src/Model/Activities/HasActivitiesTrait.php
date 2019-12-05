<?php

namespace Platformsh\Client\Model\Activities;

use Platformsh\Client\Model\Activity;

/**
 * A trait meant to be added to a Resource so it can implement HasActivitiesInterface.
 *
 * @see HasActivitiesInterface
 */
trait HasActivitiesTrait {

    /**
     * {@inheritDoc}
     */
    public function getActivity($id)
    {
        return Activity::get($id, $this->getUri() . '/activities', $this->client);
    }

    /**
     * {@inheritDoc}
     */
    public function getActivities($limit = 0, $type = null, $startsAt = null)
    {
        $options = [];
        if ($type !== null) {
            $options['query']['type'] = $type;
        }
        if ($startsAt !== null) {
            $options['query']['starts_at'] = Activity::formatStartsAt($startsAt);
        }

        $activities = Activity::getCollection($this->getUri() . '/activities', $limit, $options, $this->client);

        // Guarantee the type filter (works around a temporary bug).
        if ($type !== null) {
            $activities = array_filter($activities, function (Activity $activity) use ($type) {
                return $activity->type === $type;
            });
        }

        return $activities;
    }
}
