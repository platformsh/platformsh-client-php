<?php

namespace Platformsh\Client\Model;

use GuzzleHttp\Exception\ConnectException;

class Activity extends Resource
{

    const STATUS_SUCCESS = 'success';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_PENDING = 'pending';
    const STATUS_FAILURE = 'failure';

    /**
     * Wait for the activity to complete.
     *
     * @param callable $logger A function that will print new activity log
     *                         messages as they are received.
     * @param int|float $pollInterval The polling interval, in seconds.
     */
    public function wait(callable $logger = null, $pollInterval = 1)
    {
        $log = $this->getProperty('log');
        if (strlen(trim($log))) {
            $logger(trim($log) . "\n");
        }
        $length = strlen($log);
        $retries = 0;
        while (!$this->isComplete() && $this->getState() !== self::STATUS_FAILURE) {
            usleep($pollInterval * 1000000);
            try {
                $this->refresh(['timeout' => $pollInterval]);
                if ($new = substr($this->getProperty('log'), $length)) {
                    $logger(trim($new) . "\n");
                    $length += strlen($new);
                }
            }
            catch (ConnectException $e) {
                // Retry on timeout.
                if (strpos($e->getMessage(), 'cURL error 28') !== false && $retries <= 5) {
                    $retries++;
                    continue;
                }
                throw $e;
            }
        }
    }

    /**
     * Determine whether the activity is complete.
     *
     * @return bool
     */
    public function isComplete()
    {
        return $this->getCompletionPercent() >= 100;
    }

    /**
     * Get the completion progress of the activity, in percent.
     *
     * @return int
     */
    public function getCompletionPercent()
    {
        return (int) $this->getProperty('completion_percent');
    }

    /**
     * Get the state of the activity.
     *
     * This could be one of Activity::STATUS_SUCCESS,
     * Activity::STATUS_IN_PROGRESS, Activity::PENDING, or Activity::FAILURE.
     *
     * @return string
     */
    public function getState()
    {
        return $this->getProperty('state');
    }
}
