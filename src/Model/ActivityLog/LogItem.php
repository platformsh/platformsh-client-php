<?php

namespace Platformsh\Client\Model\ActivityLog;

class LogItem {
    private $timestamp;
    private $message;
    private $id;

    /**
     * LogItem constructor.
     *
     * @param string $timestamp
     * @param string $message
     * @param string $id
     */
    public function __construct($timestamp, $message, $id = '')
    {
        $this->timestamp = $timestamp;
        $this->message = $message;
        $this->id = $id;
    }

    /**
     * @param string $str
     *
     * @deprecated use LogItem::multipleFromJsonStreamWithSeal() instead
     *
     * @return LogItem|FALSE
     *   The log item, or FALSE if there is not enough information.
     */
    public static function singleFromJson($str)
    {
        $data = static::decode($str);
        if (isset($data['data']['timestamp'], $data['data']['message'])) {
            $id = isset($data['_id']) ? (string) $data['_id'] : '';
            return new static($data['data']['timestamp'], $data['data']['message'], $id);
        }
        return false;
    }

    /**
     * @param string $str
     *
     * @deprecated use LogItem::multipleFromJsonStreamWithSeal() instead
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
     * @param string $str
     * @return array
     */
    private static function decode($str)
    {
        $data = json_decode($str, true);
        if ($data === null) {
            throw new \RuntimeException('Failed to decode JSON with message: ' . json_last_error_msg() . ':' . "\n" . $data);
        }
        return $data;
    }

    /**
     * Decodes the log stream into log items and whether the "seal" was reached.
     *
     * The seal 🦭 guarantees that the log has ended.
     *
     * @param string $str
     *
     * @return array{'items': static[], 'seal': bool}
     */
    public static function multipleFromJsonStreamWithSeal($str)
    {
        $items = [];
        $seal = false;
        foreach (explode("\n", trim($str, "\n")) as $line) {
            if ($line === '') {
                continue;
            }
            $data = static::decode($line);
            if (!empty($data['seal'])) {
                $seal = true;
            }
            if (isset($data['data']['timestamp'], $data['data']['message'])) {
                $id = isset($data['_id']) ? (string) $data['_id'] : '';
                $items[] = new static($data['data']['timestamp'], $data['data']['message'], $id);
            }
        }

        return ['items' => $items, 'seal' => $seal];
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

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }
}
