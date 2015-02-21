<?php

namespace Platformsh\Client\Session;

use Platformsh\Client\Session\Storage\SessionStorageInterface;

class Session implements SessionInterface
{

    protected $id;
    protected $data;
    protected $storage;
    protected $loadNeededStorage = false;

    /**
     * @param string                  $id
     * @param array                   $data
     * @param SessionStorageInterface $storage
     */
    public function __construct($id, array $data = [], SessionStorageInterface $storage = null)
    {
        $this->setId($id);
        $this->setData($data);
        $this->storage = $storage;
    }

    /**
     * @param SessionStorageInterface $storage
     */
    public function setStorage(SessionStorageInterface $storage)
    {
        $this->storage = $storage;
        if ($this->loadNeededStorage) {
            $this->loadNeededStorage = false;
            $this->load();
        }
    }

    public function load()
    {
        if (!isset($this->storage)) {
            $this->loadNeededStorage = true;

            return false;
        }

        return $this->storage->load($this);
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
}
