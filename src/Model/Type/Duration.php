<?php

namespace Platformsh\Client\Model\Type;

/**
 * Mimics the "duration" type in the API.
 */
class Duration
{
    private $seconds;

    private static $suffixes;

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
        foreach (array_reverse(self::suffixes()) as $suffix => $unit) {
            if ($this->seconds % $unit === 0) {
                return sprintf('%s%s', $this->seconds / $unit, $suffix);
            }
        }

        return (string) $this->seconds;
    }

    /**
     * List valid date/time suffixes.
     *
     * @todo define in the static variable declaration in PHP 5.6+, or in a private constant in PHP 7.1+
     *
     * @return array An array whose keys are suffixes and whose values are
     * durations in seconds.
     */
    public static function suffixes()
    {
        if (isset(self::$suffixes)) {
            return self::$suffixes;
        }

        return self::$suffixes = [
            's' => 1,
            'm' => 60,
            'h' => 60 * 60,
            'd' => 24 * 60 * 60,
            'w' => 7 * 24 * 60 * 60,
            'M' => 30 * 24 * 60 * 60,
            'y' => 365 * 24 * 60 * 60,
        ];
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
        if (isset(self::suffixes()[substr($duration, -1)])) {
            $amount = substr($duration, 0, strlen($duration) - 1);
            $unit = self::suffixes()[substr($duration, -1)];
        } else {
            $unit = 1;
            $amount = $duration;
        }

        if (!is_numeric($amount)) {
            throw new \InvalidArgumentException('Invalid duration: ' . $duration);
        }

        return $unit * $amount;
    }

    /**
     * Compares the current Duration object to another one.
     *
     * @param \Platformsh\Client\Model\Type\Duration $other
     *
     * @return int
     *     0 if the durations are equal, 1 if the current duration is greater,
     *     and -1 if the $other duration is greater.
     */
    public function compare(Duration $other)
    {
        $a = $this->getSeconds();
        $b = $other->getSeconds();

        // In a future version (PHP 7+) this can be replaced with $a <=> $b.
        return $a > $b ? 1 : ($b > $a ? -1 : 0);
    }
}
