<?php

namespace Platformsh\Client\DataStructure;

/**
 * Apply this trait to a class to get read-only properties, with magic getters.
 *
 * The properties can be documented (with their expected types) in the class's
 * docblock, via "@property-read" annotations. Types are not enforced.
 */
trait ReadOnlyStructureTrait
{
    private $data = [];

    /**
     * Private constructor. Instantiate this object using self::fromData().
     *
     * @param array $data
     */
    private function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Magic getter.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function __get($name)
    {
        $this->checkExists($name);

        return $this->data[$name];
    }

    /**
     * Magic isset() support.
     *
     * @param string $name
     *
     * @return bool
     */
    public function __isset($name) {
        return isset($this->data[$name]);
    }

    /**
     * Magic setter.
     *
     * @param string $name
     * @param mixed  $value
     *
     * @throws \InvalidArgumentException if the property is not found
     * @throws \BadMethodCallException if the property is found
     */
    public function __set($name, $value)
    {
        $this->checkExists($name);
        throw new \BadMethodCallException('Property not writable: ' . $name);
    }

    /**
     * Check if a property exists.
     *
     * @param string $property
     *
     * @throws \InvalidArgumentException if the property is not found
     */
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
