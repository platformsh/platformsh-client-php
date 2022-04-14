<?php

namespace Platformsh\Client\Model;

use DateTime;
use DateTimeZone;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\Stream\StreamInterface;
use Platformsh\Client\Model\ActivityLog\LogItem;

/**
 * An activity on a Platform.sh environment.
 *
 * Activities are triggered by environment operations (such as 'branch' or
 * 'merge').
 *
 * @property-read string   $id
 * @property-read int      $completion_percent
 * @property-read string   $log Deprecated: use readLog() instead.
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
 * @property-read string   $text The plain-text description of the activity.
 * @property-read array    $payload
 */
class Activity extends Resource
{

    const RESULT_SUCCESS = 'success';
    const RESULT_FAILURE = 'failure';

    const STATE_COMPLETE = 'complete';
    const STATE_IN_PROGRESS = 'in_progress';
    const STATE_PENDING = 'pending';
    const STATE_CANCELLED = 'cancelled';

    /**
     * Wait for the activity to complete.
     *
     * @param callable  $onPoll       A function that will be called every time
     *                                the activity is polled for updates. It
     *                                will be passed one argument: the
     *                                Activity object.
     * @param callable  $onLog        A function that will print new activity log
     *                                messages as they are received. It will be
     *                                passed one argument: the message as a
     *                                string. Deprecated: use readLog() instead.
     * @param int|float $pollInterval The polling interval, in seconds.
     */
    public function wait(callable $onPoll = null, callable $onLog = null, $pollInterval = 1)
    {
        $log = $this->getProperty('log');
        $length = strlen($log);
        if ($onLog !== null) {
            @trigger_error('The $onLog parameter is deprecated. Use the readLog() method instead.', E_USER_DEPRECATED);
            if ($length > 0) {
                $onLog($log);
            }
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
     * Allows reading the streaming activity log.
     *
     * @param callable|NULL $onUpdate
     *   A callback that receives an array of LogItem objects when there are
     *   new ones available. Usually this will be 0 items or 1 item.
     *
     * @see LogItem
     *
     * @return LogItem[]
     */
    public function readLog(callable $onUpdate = null)
    {
        $response = $this->fetchLog($onUpdate !== null);
        $body = $response->getBody();
        if ($body === null) {
            throw new \RuntimeException('No response body found');
        }
        if ($onUpdate !== null) {
            while ($line = $this->readline($body)) {
                $onUpdate(LogItem::multipleFromJsonStream($line));
            }
        }

        return LogItem::multipleFromJsonStream($body->__toString());
    }

    /**
     * Reads the next line of a stream.
     *
     * @param StreamInterface $stream
     * @param string $newline
     *
     * @return string
     */
    private function readline(StreamInterface $stream, $newline = "\n") {
        $buffer = '';
        while (!$stream->eof()) {
            $byte = $stream->read(1);
            $buffer .= $byte;
            if ($byte === $newline) {
                break;
            }
        }

        return $buffer;
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
     * The "description" property contains the HTML-formatted description.
     * The "text" property contains the plain-text description.
     *
     * @param bool $html Whether to return HTML.
     *
     * @return string
     */
    public function getDescription($html = false)
    {
        return $this->getProperty($html ? 'description' : 'text');
    }

    /**
     * Formats a timestamp as RFC3339, to be used in the API's starts_at parameter.
     *
     * @param int|DateTime $timestamp UNIX UTC timestamp (seconds) or DateTime
     *
     * @throws \RuntimeException if the timestamp is invalid
     *
     * @return string 2022-02-22T02:00:00.000000+00:00
     */
    public static function formatStartsAt($timestamp)
    {
        if ($timestamp instanceof DateTime) {
            // Override the timezone to produce a UTC ISO date
            $date = clone $timestamp;
            $date->setTimezone(new DateTimeZone("UTC"));
        } else {
            // Parse the UNIX UTC timestamp (seconds) into a DateTime
            $date = DateTime::createFromFormat('U', $timestamp, new DateTimeZone("UTC"));
        }

        if (!$date) {
            throw new \RuntimeException(sprintf('Failed to format timestamp: %d', $timestamp));
        }

        # Sample: 2022-02-22T02:00:00.000000+00:00
        return $date->format('Y-m-d\TH:i:s.uP');
    }

    /**
     * Fetches the activity log as a Guzzle streaming response.
     *
     * @param bool $stream
     *   Whether to stream the response rather than download it all.
     * @param int $startAt
     *   The item to start with.
     * @param int $maxItems
     *   How many items to retrieve. Leave at 0 to fetch all items.
     * @param int $maxDelay
     *   How long to wait for new messages (on the server side). Use -1 to wait forever.
     *
     * @return ResponseInterface
     */
    private function fetchLog($stream = true, $startAt = 0, $maxItems = 0, $maxDelay = -1)
    {
        return $this->client->get($this->getLink('log'), [
            'query' => [
                'start_at' => $startAt,
                'max_items' => $maxItems,
                'max_delay' => $maxDelay,
            ],
            'stream' => $stream,
        ]);
    }
}
