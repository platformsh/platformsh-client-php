<?php

declare(strict_types=1);

namespace Platformsh\Client\Model\Activities;

use DateTime;
use Platformsh\Client\Model\Activity;

/**
 * A trait meant to be added to a Resource so it can implement HasActivitiesInterface.
 *
 * @see HasActivitiesInterface
 */
trait HasActivitiesTrait
{
    public function getActivity(string $id): Activity|false
    {
        return Activity::get($id, $this->getUri() . '/activities', $this->client);
    }

    public function getActivities(int $limit = 0, array|string $type = null, DateTime|int $startsAt = null, array|string $state = null, array|string $result = null): array
    {
        $query = '';
        if ($type !== null) {
            foreach ((array) $type as $typeItem) {
                $query .= '&type=' . \rawurlencode($typeItem);
            }
        }
        if ($startsAt !== null) {
            $query .= '&starts_at=' . Activity::formatStartsAt($startsAt);
        }
        if (! empty($limit)) {
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
