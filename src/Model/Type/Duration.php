<?php

declare(strict_types=1);

namespace Platformsh\Client\Model\Type;

/**
 * Mimics the "duration" type in the API.
 */
class Duration
{
    public static array $suffixes = [
        's' => 1,
        'm' => 60,
        'h' => 60 * 60,
        'd' => 24 * 60 * 60,
        'w' => 7 * 24 * 60 * 60,
        'M' => 30 * 24 * 60 * 60,
        'y' => 365 * 24 * 60 * 60,
    ];

    private int|float $seconds;

    public function __construct(int|string $duration)
    {
        $this->seconds = self::stringToSeconds((string) $duration);
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
     * Returns the duration as a number of seconds.
     */
    public function getSeconds(): float|int
    {
        return $this->seconds;
    }

    /**
     * Compares the current Duration object to another one.
     *
     * @return int
     *     0 if the durations are equal, 1 if the current duration is greater,
     *     and -1 if the $other duration is greater.
     */
    public function compare(self $other): int
    {
        return $this->getSeconds() <=> $other->getSeconds();
    }

    /**
     * Converts a duration string to seconds.
     */
    private static function stringToSeconds(string $duration): float|int
    {
        if (isset(self::$suffixes[substr($duration, -1)])) {
            $amount = substr($duration, 0, strlen($duration) - 1);
            $unit = self::$suffixes[substr($duration, -1)];
        } else {
            $unit = 1;
            $amount = $duration;
        }

        if (! is_numeric($amount)) {
            throw new \InvalidArgumentException('Invalid duration: ' . $duration);
        }

        return $unit * $amount;
    }
}
