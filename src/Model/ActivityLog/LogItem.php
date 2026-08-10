<?php

declare(strict_types=1);

namespace Platformsh\Client\Model\ActivityLog;

class LogItem
{
    private string $timestamp;

    private string $message;

    private string $id;

    public function __construct(string $timestamp, string $message, string $id = '')
    {
        $this->timestamp = $timestamp;
        $this->message = $message;
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->message;
    }

    /**
     * @return LogItem|false
     *   The log item, or FALSE if there is not enough information.
     *@deprecated use LogItem::multipleFromJsonStreamWithSeal() instead
     */
    public static function singleFromJson(string $str): self|false
    {
        $data = self::decode($str);
        if (isset($data['data']['timestamp'], $data['data']['message'])) {
            $id = isset($data['_id']) ? (string) $data['_id'] : '';
            return new static($data['data']['timestamp'], $data['data']['message'], $id);
        }
        return false;
    }

    /**
     * @return self[]
     *@deprecated use LogItem::multipleFromJsonStreamWithSeal() instead
     */
    public static function multipleFromJsonStream(string $str): array
    {
        $items = [];
        foreach (explode("\n", trim($str, "\n")) as $line) {
            if ($line === '') {
                continue;
            }
            $item = static::singleFromJson($line);
            if ($item !== false) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Decodes the log stream into log items and whether the "seal" was reached.
     *
     * The seal 🦭 guarantees that the log has ended.
     *
     * @return array{'items': static[], 'seal': bool}
     */
    public static function multipleFromJsonStreamWithSeal(string $str): array
    {
        $items = [];
        $seal = false;
        foreach (explode("\n", trim($str, "\n")) as $line) {
            if ($line === '') {
                continue;
            }
            $data = self::decode($line);
            if (is_array($data)) {
                if (! empty($data['seal'])) {
                    $seal = true;
                }
                if (isset($data['data']['timestamp'], $data['data']['message'])) {
                    $id = isset($data['_id']) ? (string) $data['_id'] : '';
                    $items[] = new static($data['data']['timestamp'], $data['data']['message'], $id);
                }
            }
        }

        return [
            'items' => $items,
            'seal' => $seal,
        ];
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @throws \Exception
     */
    public function getTime(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->timestamp);
    }

    public function getId(): string
    {
        return $this->id;
    }

    private static function decode(string $str): mixed
    {
        $data = json_decode($str, true);
        if ($data === null) {
            trigger_error(sprintf('Failed to decode JSON line with message: %s: %s', json_last_error_msg(), $str), E_USER_WARNING);
        }
        return $data;
    }
}
