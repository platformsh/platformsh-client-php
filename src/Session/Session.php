<?php

namespace Platformsh\Client\Session;

use Platformsh\Client\Session\Storage\SessionStorageInterface;

class Session implements SessionInterface
{

    /** @var string */
    private $id;

    /** @var array */
    private $data = [];

    /** @var array */
    private $original = [];

    /** @var bool */
    private $loaded = false;

    /** @var SessionStorageInterface|null */
    private $storage;

    /**
     * @param string                  $id   A unique session ID.
     * @param array                   $data Initial session data.
     * @param SessionStorageInterface $storage
     */
    public function __construct($id = 'default', array $data = [], SessionStorageInterface $storage = null)
    {
        $this->id = $id;
        $this->data = $data;
        $this->storage = $storage;
    }

    /**
     * Load session data, if storage is defined.
     */
    private function lazyLoad()
    {
        if (!$this->loaded && isset($this->storage)) {
            $this->data = $this->storage->load($this->id);
            $this->original = $this->data;
            $this->loaded = true;
        }
    }

    /**
     * @inheritdoc
     */
    public function setStorage(SessionStorageInterface $storage)
    {
        $this->storage = $storage;
    }

    /**
     * @inheritdoc
     */
    public function set($key, $value)
    {
        if (is_object($value) && !$value instanceof \JsonSerializable) {
            throw new \InvalidArgumentException('Invalid session data type: object');
        }
        $this->lazyLoad();
        $this->data[$key] = $value;
    }

    /**
     * @inheritdoc
     */
    public function get($key)
    {
        $this->lazyLoad();

        return isset($this->data[$key]) ? $this->data[$key] : null;
    }

    /**
     * @inheritdoc
     */
    public function clear()
    {
        $this->lazyLoad();
        $this->data = [];
    }

    /**
     * @inheritdoc
     */
    public function save()
    {
        if (!isset($this->storage)) {
            return;
        }
        $this->lazyLoad();
        if ($this->data === $this->original) {
            return;
        }

        $this->storage->save($this->id, $this->data);
    }
}
