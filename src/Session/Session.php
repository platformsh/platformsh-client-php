<?php

namespace Platformsh\Client\Session;

use Platformsh\Client\Session\Storage\SessionStorageInterface;

class Session implements SessionInterface
{

    protected $id;
    protected $data;
    protected $loaded = false;
    protected $storage;

    /**
     * @param string                  $id
     * @param array                   $data
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
     * @param SessionStorageInterface $storage
     */
    public function setStorage(SessionStorageInterface $storage)
    {
        $this->storage = $storage;
        $this->load();
    }

    public function load($reload = false)
    {
        if ($this->loaded && !$reload) {
            return true;
        } elseif (!isset($this->storage)) {
            return false;
        }

        return $this->loaded = $this->storage->load($this);
    }

    public function set($key, $value)
    {
        if (is_object($value)) {
            throw new \InvalidArgumentException('Invalid session data type');
        }
        $this->data[$key] = $value;
    }

    public function get($key)
    {
        return isset($this->data[$key]) ? $this->data[$key] : false;
    }

    public function getData()
    {
        return $this->data;
    }

    public function setData(array $data)
    {
        $this->data = $data;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function save()
    {
        if (!isset($this->storage)) {
            return false;
        }

        return $this->storage->save($this);
    }

    public function __destruct()
    {
        $this->save();
    }
}
