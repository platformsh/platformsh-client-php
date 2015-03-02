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
     * @param callable  $logger       A function that will print new activity log
     *                                messages as they are received.
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
            } catch (ConnectException $e) {
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

    /**
     * Restore the backup associated with this activity.
     *
     * @return Activity
     */
    public function restore()
    {
        if ($this->getProperty('type') !== 'environment.backup') {
            throw new \BadMethodCallException('Cannot restore activity (wrong type)');
        }
        if (!$this->isComplete()) {
            throw new \BadMethodCallException('Cannot restore backup (not complete)');
        }
        return $this->runLongOperation('restore');
    }

    /**
     * Get a human-readable description of the activity.
     *
     * @return string
     */
    public function getDescription()
    {
        $type = $this->getProperty('type');
        $payload = $this->getProperty('payload');
        switch ($type) {
            case 'environment.activate':
                return sprintf(
                  "%s activated environment %s",
                  $payload['user']['display_name'],
                  $payload['environment']['title']
                );

            case 'environment.backup':
                return sprintf(
                  "%s created backup of %s",
                  $payload['user']['display_name'],
                  $payload['environment']['title']
                );

            case 'environment.branch':
                return sprintf(
                  "%s branched %s from %s",
                  $payload['user']['display_name'],
                  $payload['outcome']['title'],
                  $payload['parent']['title']
                );

            case 'environment.delete':
                return sprintf(
                  "%s deleted environment %s",
                  $payload['user']['display_name'],
                  $payload['environment']['title']
                );

            case 'environment.deactivate':
                return sprintf(
                  "%s deactivated environment %s",
                  $payload['user']['display_name'],
                  $payload['environment']['title']
                );

            case 'environment.initialize':
                return sprintf(
                  "%s initialized environment %s with profile %s",
                  $payload['user']['display_name'],
                  $payload['outcome']['title'],
                  $payload['profile']
                );

            case 'environment.merge':
                return sprintf(
                  "%s merged %s into %s",
                  $payload['user']['display_name'],
                  $payload['outcome']['title'],
                  $payload['environment']['title']
                );

            case 'environment.push':
                return sprintf(
                  "%s pushed to %s",
                  $payload['user']['display_name'],
                  $payload['environment']['title']
                );

            case 'environment.restore':
                return sprintf(
                  "%s restored %s to %s",
                  $payload['user']['display_name'],
                  $payload['environment'],
                  substr($payload['commit'], 0, 7)
                );

            case 'environment.synchronize':
                $syncedCode = !empty($payload['synchronize_code']);
                if ($syncedCode && !empty($payload['synchronize_data'])) {
                    $syncType = 'code and data';
                } elseif ($syncedCode) {
                    $syncType = 'code';
                } else {
                    $syncType = 'data';
                }
                return sprintf(
                  "%s synced %s's %s with %s",
                  $payload['user']['display_name'],
                  $payload['outcome']['title'],
                  $syncType,
                  $payload['environment']['title']
                );
        }
        return $type;
    }
}
