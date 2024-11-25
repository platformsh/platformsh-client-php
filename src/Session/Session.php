<?php

declare(strict_types=1);

namespace Platformsh\Client\Session;

use Platformsh\Client\Session\Storage\SessionStorageInterface;

class Session implements SessionInterface
{
    private string $id;

    private array $data;

    private array $original = [];

    private bool $loaded = false;

    private ?SessionStorageInterface $storage;

    /**
     * @param string $id   A unique session ID.
     * @param array                   $data Initial session data.
     */
    public function __construct(string $id = 'default', array $data = [], SessionStorageInterface $storage = null)
    {
        $this->id = $id;
        $this->data = $data;
        $this->storage = $storage;
    }

    public function setStorage(SessionStorageInterface $storage): void
    {
        $this->storage = $storage;
    }

    public function set(string $key, mixed $value): void
    {
        if (is_object($value) && ! $value instanceof \JsonSerializable) {
            throw new \InvalidArgumentException('Invalid session data type: object');
        }
        $this->lazyLoad();
        $this->data[$key] = $value;
    }

    public function get(string $key): mixed
    {
        $this->lazyLoad();

        return $this->data[$key] ?? null;
    }

    public function clear(): void
    {
        $this->lazyLoad();
        $this->data = [];
    }

    public function save(): void
    {
        if (! isset($this->storage)) {
            return;
        }
        $this->lazyLoad();
        if ($this->data === $this->original) {
            return;
        }

        $this->storage->save($this->id, $this->data);
    }

    /**
     * Load session data, if storage is defined.
     */
    private function lazyLoad(): void
    {
        if (! $this->loaded && isset($this->storage)) {
            $this->data = $this->storage->load($this->id);
            $this->original = $this->data;
            $this->loaded = true;
        }
    }
}
