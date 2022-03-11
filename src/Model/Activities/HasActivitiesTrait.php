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
    public function getActivities($limit = 0, $type = null, $startsAt = null, $state = null, $result = null)
    {
        $query = '';
        if ($type !== null) {
            $query .= '&type=' . \rawurlencode($type);
        }
        if ($startsAt !== null) {
            $query .= '&starts_at=' . Activity::formatStartsAt($startsAt);
        }
        if (!empty($limit)) {
            $query .= '&count=' . $limit;
        }
        if ($result !== null) {
            foreach ((array) $result as $resultItem) {
                $query .= '&result=' . \rawurlencode($resultItem);
            }
        }
        if ($state !== null) {
            foreach ((array) $state as $stateItem) {
                $query .= '&state=' . \rawurlencode($stateItem);
            }
        }
        if ($query !== '') {
            $query = '?' . \substr($query, 1);
        }

        return Activity::getCollection($this->getUri() . '/activities' . $query, $limit, [], $this->client);
    }
}
