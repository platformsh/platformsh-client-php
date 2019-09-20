<?php

namespace Platformsh\Client\Model\ActivityLog;

class LogItem {
    private $timestamp;
    private $message;

    /**
     * LogItem constructor.
     *
     * @param string $timestamp
     * @param string $message
     */
    public function __construct($timestamp, $message)
    {
        $this->timestamp = $timestamp;
        $this->message = $message;
    }

    /**
     * @param string $str
     *
     * @return LogItem|FALSE
     *   The log item, or FALSE if there is not enough information.
     */
    public static function singleFromJson($str)
    {
        $data = json_decode($str, true);
        if ($data === null) {
            throw new \RuntimeException('Failed to decode JSON with message: ' . json_last_error_msg() . ':' . "\n" . $data);
        }
        if (empty($data['data']['timestamp']) || empty($data['data']['message'])) {
            return FALSE;
        }

        return new static($data['data']['timestamp'], $data['data']['message']);
    }

    /**
     * @param string $str
     *
     * @return static[]
     */
    public static function multipleFromJsonStream($str)
    {
        $items = [];
        foreach (explode("\n", trim($str, "\n")) as $line) {
            if ($line === '') {
                continue;
            }
            $item = static::singleFromJson($line);
            if ($item !== FALSE) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->message;
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @return \DateTimeImmutable
     *
     * @throws \Exception
     */
    public function getTime()
    {
        return new \DateTimeImmutable($this->timestamp);
    }
}
