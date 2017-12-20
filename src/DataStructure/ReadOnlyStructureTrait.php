<?php

namespace Platformsh\Client\DataStructure;

trait ReadOnlyStructureTrait
{
    private $data = [];

    private function __construct(array $data)
    {
        $this->data = $data;
    }

    public function __get($name)
    {
        $this->checkExists($name);

        return $this->data[$name];
    }

    public function __set($name, $value)
    {
        $this->checkExists($name);
        throw new \BadMethodCallException('Property not writable: ' . $name);
    }

    private function checkExists($property)
    {
        if (!array_key_exists($property, $this->data)) {
            throw new \InvalidArgumentException('Property not found: ' . $property);
        }
    }

    /**
     * Construct from API data.
     *
     * @param array $data
     *
     * @return static
     */
    public static function fromData(array $data)
    {
        return new static($data);
    }

    /**
     * Get all properties.
     *
     * @return array
     */
    public function getProperties()
    {
        return $this->data;
    }
}
