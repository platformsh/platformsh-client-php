<?php

namespace Platformsh\Client\Model;

use GuzzleHttp\Exception\ConnectException;

/**
 * An activity on a Platform.sh environment.
 *
 * Activities are triggered by environment operations (such as 'branch' or
 * 'merge').
 *
 * @property-read string   $id
 * @property-read int      $completion_percent
 * @property-read string   $log
 * @property-read string   $created_at
 * @property-read string   $updated_at
 * @property-read string[] $environments
 * @property-read string   $completed_at
 * @property-read array    $parameters
 * @property-read string   $project
 * @property-read string   $state
 * @property-read string   $result
 * @property-read string   $started_at
 * @property-read string   $type
 * @property-read string   $description The HTML description of the activity.
 * @property-read array    $payload
 */
class Activity extends ApiResourceBase
{

    const RESULT_SUCCESS = 'success';
    const RESULT_FAILURE = 'failure';

    const STATE_COMPLETE = 'complete';
    const STATE_IN_PROGRESS = 'in_progress';
    const STATE_PENDING = 'pending';

    /**
     * Wait for the activity to complete.
     *
     * @todo use the FutureInterface
     *
     * @param callable  $onPoll       A function that will be called every time
     *                                the activity is polled for updates. It
     *                                will be passed one argument: the
     *                                Activity object.
     * @param callable  $onLog        A function that will print new activity log
     *                                messages as they are received. It will be
     *                                passed one argument: the message as a
     *                                string.
     * @param int|float $pollInterval The polling interval, in seconds.
     */
    public function wait(callable $onPoll = null, callable $onLog = null, $pollInterval = 1)
    {
        $log = $this->getProperty('log');
        $length = strlen($log);
        if ($onLog !== null && $length > 0) {
            $onLog($log);
        }
        $retries = 0;
        while (!$this->isComplete()) {
            usleep($pollInterval * 1000000);
            try {
                $this->refresh(['timeout' => $pollInterval + 5]);
                if ($onPoll !== null) {
                    $onPoll($this);
                }
                if ($onLog !== null && ($new = substr($this->getProperty('log'), $length))) {
                    $onLog($new);
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
     * Restore the backup associated with this activity.
     *
     * @param string|null $target     The name of the target environment to
     *                                which the backup should be restored (this
     *                                could be the name of an existing
     *                                environment, or a new environment). Leave
     *                                this null to restore to the backup's
     *                                original environment.
     * @param string|null $branchFrom If a new environment will be created
     *                                (depending on $target), this specifies
     *                                the name of the parent branch.
     *
     * @return Activity
     */
    public function restore($target = null, $branchFrom = null)
    {
        if ($this->getProperty('type') !== 'environment.backup') {
            throw new \BadMethodCallException('Cannot restore activity (wrong type)');
        }
        if (!$this->isComplete()) {
            throw new \BadMethodCallException('Cannot restore backup (not complete)');
        }

        $options = [];
        if ($target !== null) {
            $options['environment_name'] = $target;
        }
        if ($branchFrom !== null) {
            $options['branch_from'] = $branchFrom;
        }

        return $this->runLongOperation('restore', 'post', $options);
    }

    /**
     * Cancel this activity.
     */
    public function cancel()
    {
        $this->runOperation('cancel');
    }

    /**
     * Get a human-readable description of the activity.
     *
     * The "description" property contains the HTML-formatted description. This
     * method just provides another way to access it, and a way to remove HTML
     * easily.
     *
     * @param bool $html Whether to return HTML.
     *
     * @return string
     */
    public function getDescription($html = false)
    {
        $description = $this->getProperty('description');
        if ($html) {
            return $description;
        }

        return html_entity_decode(strip_tags($description), ENT_QUOTES, 'utf-8');
    }

    /**
     * @param int $timestamp
     *
     * @return false|string
     */
    public static function formatStartsAt($timestamp)
    {
        $tz = date_default_timezone_get();
        date_default_timezone_set('UTC');
        $date = date('c', $timestamp);
        date_default_timezone_set($tz);
        if (!$date) {
            throw new \RuntimeException(sprintf('Failed to format timestamp: %d', $timestamp));
        }

        return $date;
    }
}
