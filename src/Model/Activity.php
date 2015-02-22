<?php

namespace Platformsh\Client\Model;

use GuzzleHttp\Ring\Exception\ConnectException;

class Activity extends Resource
{

    const STATUS_SUCCESS = 2;
    const STATUS_PROGRESS = 1;
    const STATUS_PENDING = 0;
    const STATUS_FAILURE = -1;

    /**
     * Wait for the activity to complete.
     *
     * @param int|float $pollInterval The polling interval, in seconds.
     */
    public function wait($pollInterval = 1)
    {
        while (!$this->isComplete() && $this->getStatus() !== self::STATUS_FAILURE) {
            usleep($pollInterval * 1000000);
            try {
                $this->refresh(['timeout' => $pollInterval]);
            }
            catch (ConnectException $e) {
                // Retry on timeout.
                // @todo is this cURL status code more accessible?
                if (strpos($e->getMessage(), 'cURL error 28') !== false) {
                    continue;
                }
                throw $e;
            }
        }
    }

    public function isComplete()
    {
        return $this->getCompletionPercent() >= 100;
    }

    /**
     * @return int
     */
    public function getCompletionPercent()
    {
        return (int) $this->getProperty('completion_percent');
    }

    /**
     * @throws \Exception
     *
     * @return int
     */
    public function getStatus()
    {
        switch ($this->getProperty('state')) {
            case 'success':
                return self::STATUS_SUCCESS;

            case 'in_progress':
                return self::STATUS_PROGRESS;

            case 'pending':
                return self::STATUS_PENDING;

            case 'failed':
                return self::STATUS_FAILURE;
        }
        throw new \Exception('Status not known');
    }

}
