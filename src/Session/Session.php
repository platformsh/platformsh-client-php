<?php

namespace Platformsh\Client\Session;

use Platformsh\Client\Session\Storage\SessionStorageInterface;

class Session implements SessionInterface
{

    protected $id;
    protected $data;
    protected $original;
    protected $loaded = false;
    protected $storage;

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
        $this->load();
    }

    /**
     * @inheritdoc
     */
    public function load($reload = false)
    {
        if (!$this->loaded || $reload) {
            if (isset($this->storage)) {
                $this->storage->load($this);
                $this->original = $this->data;
                $this->loaded = true;
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function setStorage(SessionStorageInterface $storage)
    {
        $this->storage = $storage;
        $this->load();
    }

    /**
     * @inheritdoc
     */
    public function add(array $data)
    {
        $this->data = array_merge($this->data, $data);
    }

    /**
     * @inheritdoc
     */
    public function set($key, $value)
    {
        if (is_object($value)) {
            throw new \InvalidArgumentException('Invalid session data type: object');
        }
        $this->data[$key] = $value;
    }

    /**
     * @inheritdoc
     */
    public function get($key)
    {
        return isset($this->data[$key]) ? $this->data[$key] : false;
    }

    /**
     * @inheritdoc
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @inheritdoc
     */
    public function setData(array $data)
    {
        $this->data = $data;
    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @inheritdoc
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @inheritdoc
     */
    public function clear()
    {
        $this->data = [];
    }

    /**
     * @inheritdoc
     */
    public function save()
    {
        if (!isset($this->storage) || $this->data === $this->original) {
            return;
        }

        $this->storage->save($this);
    }
}
