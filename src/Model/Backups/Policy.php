<?php

declare(strict_types=1);

namespace Platformsh\Client\Model\Backups;

use Platformsh\Client\Model\Type\Duration;

class Policy
{
    private string|int $interval;

    private int $count;

    /**
     * Constructs a backup policy instance.
     *
     * @param int|string $interval
     *   The policy interval specification.
     * @param int $count
     *   The number of backups to keep under this policy.
     */
    public function __construct(int|string $interval, int $count)
    {
        $this->interval = $interval;
        $this->count = $count;
    }

    /**
     * Get the configured interval.
     */
    public function getInterval(): int|string
    {
        return $this->interval;
    }

    /**
     * Get the configured number of backups to keep.
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * Get the configured interval, in seconds.
     */
    public function getIntervalAsSeconds(): int
    {
        return (new Duration($this->interval))->getSeconds();
    }
}
