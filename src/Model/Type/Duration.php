<?php

namespace Platformsh\Client\Model\Type;

/**
 * Mimics the "duration" type in the API.
 */
class Duration
{
    private $seconds;

    public static $suffixes = [
        's' => 1,
        'm' => 60,
        'h' => 60 * 60,
        'd' => 24 * 60 * 60,
        'w' => 7 * 24 * 60 * 60,
        'M' => 30 * 24 * 60 * 60,
        'y' => 365 * 24 * 60 * 60,
    ];

    /**
     * @param int|string $duration
     */
    public function __construct($duration)
    {
        $this->seconds = self::stringToSeconds((string) $duration);
    }

    /**
     * Returns the duration as a number of seconds.
     *
     * @return int|float
     */
    public function getSeconds()
    {
        return $this->seconds;
    }

    /**
     * Returns the duration as a string.
     *
     * @return string
     */
    public function __toString()
    {
        foreach (array_reverse(self::$suffixes) as $suffix => $unit) {
            if ($this->seconds % $unit === 0) {
                return sprintf('%s%s', $this->seconds / $unit, $suffix);
            }
        }

        return (string) $this->seconds;
    }

    /**
     * Converts a duration string to seconds.
     *
     * @param string $duration
     *
     * @return int|float
     */
    private static function stringToSeconds($duration)
    {
        if (isset(self::$suffixes[substr($duration, -1)])) {
            $amount = substr($duration, 0, strlen($duration) - 1);
            $unit = self::$suffixes[substr($duration, -1)];
        } else {
            $unit = 1;
            $amount = $duration;
        }

        if (!is_numeric($amount)) {
            throw new \InvalidArgumentException('Invalid duration: ' . $duration);
        }

        return $unit * $amount;
    }
}
