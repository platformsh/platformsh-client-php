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
class Activity extends Resource
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
        if ($onLog !== null && strlen(trim($log))) {
            $onLog(trim($log) . "\n");
        }
        $length = strlen($log);
        $retries = 0;
        while (!$this->isComplete()) {
            usleep($pollInterval * 1000000);
            try {
                $this->refresh(['timeout' => $pollInterval + 5]);
                if ($onPoll !== null) {
                    $onPoll($this);
                }
                if ($onLog !== null && ($new = substr($this->getProperty('log'), $length))) {
                    $onLog(trim($new) . "\n");
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
     * @param bool $html Whether to include HTML in the output.
     *
     * @deprecated
     *   Use the "description" property instead, for a description wrapped in
     *   HTML tags. Or for plain text, just run it through strip_tags().
     *
     * @return string
     */
    public function getDescription($html = false)
    {
        if ($this->hasProperty('description', false)) {
            $description = $this->getProperty('description');

            return $html ? $description : strip_tags($description);
        }

        $type = $this->getProperty('type');
        $payload = $this->getProperty('payload');
        switch ($type) {
            case 'project.domain.create':
                return sprintf(
                    '%s added domain %s',
                    $payload['user']['display_name'],
                    $payload['domain']['name']
                );

            case 'project.domain.delete':
                return sprintf(
                    '%s deleted domain %s',
                    $payload['user']['display_name'],
                    $payload['domain']['name']
                );

            case 'project.domain.update':
                return sprintf(
                    '%s updated domain %s',
                    $payload['user']['display_name'],
                    $payload['domain']['name']
                );

            case 'project.modify.title':
                return sprintf(
                    '%s changed project name to %s',
                    $payload['user']['display_name'],
                    $payload['new_title']
                );

            case 'environment.activate':
                return sprintf(
                    '%s activated environment %s',
                    $payload['user']['display_name'],
                    $payload['environment']['title']
                );

            case 'environment.backup':
                return sprintf(
                    '%s created a snapshot of %s',
                    $payload['user']['display_name'],
                    $payload['environment']['title']
                );

            case 'environment.branch':
                return sprintf(
                    '%s branched %s from %s',
                    $payload['user']['display_name'],
                    $payload['outcome']['title'],
                    $payload['parent']['title']
                );

            case 'environment.delete':
                return sprintf(
                    '%s deleted environment %s',
                    $payload['user']['display_name'],
                    $payload['environment']['title']
                );

            case 'environment.deactivate':
                return sprintf(
                    '%s deactivated environment %s',
                    $payload['user']['display_name'],
                    $payload['environment']['title']
                );

            case 'environment.initialize':
                return sprintf(
                    '%s initialized environment %s with profile %s',
                    $payload['user']['display_name'],
                    $payload['environment']['title'],
                    $payload['profile']
                );

            case 'environment.merge':
                return sprintf(
                    '%s merged %s into %s',
                    $payload['user']['display_name'],
                    $payload['outcome']['title'],
                    $payload['environment']['title']
                );

            case 'environment.push':
                return sprintf(
                    '%s pushed to %s',
                    $payload['user']['display_name'],
                    $payload['environment']['title']
                );

            case 'environment.redeploy':
                return sprintf(
                  '%s redeployed environment %s',
                  $payload['user']['display_name'],
                  $payload['environment']['title']
                );

            case 'environment.restore':
                return sprintf(
                    '%s restored %s from snapshot %s',
                    $payload['user']['display_name'],
                    $payload['environment'],
                    substr($payload['backup_name'], 0, 7)
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

            case 'environment.access.add':
                return sprintf(
                    '%s added %s to %s',
                    $payload['user']['display_name'],
                    $payload['access']['display_name'],
                    $payload['environment']['title']
                );

            case 'environment.access.remove':
                return sprintf(
                    '%s removed %s from %s',
                    $payload['user']['display_name'],
                    $payload['access']['display_name'],
                    $payload['environment']['title']
                );

            case 'environment.variable.create':
                return sprintf(
                    '%s added environment variable %s',
                    $payload['user']['display_name'],
                    $payload['variable']['name']
                );

            case 'environment.variable.delete':
                return sprintf(
                    '%s deleted environment variable %s',
                    $payload['user']['display_name'],
                    $payload['variable']['name']
                );

            case 'environment.variable.update':
                return sprintf(
                    '%s modified environment variable %s',
                    $payload['user']['display_name'],
                    $payload['variable']['name']
                );

            case 'environment.update.http_access':
                return sprintf(
                    '%s updated HTTP Access settings on environment %s',
                    $payload['user']['display_name'],
                    $payload['environment']['title']
                );

            case 'environment.route.create':
                return sprintf(
                    '%s added route %s',
                    $payload['user']['display_name'],
                    $payload['route']['id']
                );

            case 'environment.route.delete':
                return sprintf(
                    '%s deleted route %s',
                    $payload['user']['display_name'],
                    $payload['route']['id']
                );

            case 'environment.route.update':
                return sprintf(
                    '%s modified route %s',
                    $payload['user']['display_name'],
                    $payload['route']['id']
                );

            case 'environment.subscription.update':
                return sprintf(
                    '%s modified subscription',
                    $payload['user']['display_name']
                );

            case 'environment.update.restrict_robots':
                $indexingAllowed = !$payload['environment']['restrict_robots'];
                $verb = $indexingAllowed ? 'enabled' : 'disabled';

                return sprintf(
                    '%s %s indexing by search engines on environment %s',
                    $payload['user']['display_name'],
                    $verb,
                    $payload['environment']['title']
                );

            case 'environment.update.smtp':
                return sprintf(
                    '%s updated SMTP settings on environment %s',
                    $payload['user']['display_name'],
                    $payload['environment']['title']
                );

            case 'project.create':
                return sprintf(
                    '%s created a new project %s',
                    $payload['user']['display_name'],
                    $payload['outcome']['title']
                );

            case 'project.variable.create':
                return sprintf(
                    '%s added project variable %s',
                    $payload['user']['display_name'],
                    $payload['variable']['name']
                );

            case 'project.variable.delete':
                return sprintf(
                    '%s deleted project variable %s',
                    $payload['user']['display_name'],
                    $payload['variable']['name']
                );

            case 'project.variable.update':
                return sprintf(
                    '%s modified project variable %s',
                    $payload['user']['display_name'],
                    $payload['variable']['name']
                );
        }
        return $type;
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
