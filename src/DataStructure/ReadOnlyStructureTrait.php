<?php

declare(strict_types=1);

namespace Platformsh\Client\DataStructure;

/**
 * Apply this trait to a class to get read-only properties, with magic getters.
 *
 * The properties can be documented (with their expected types) in the class's
 * docblock, via "@property-read" annotations. Types are not enforced.
 */
trait ReadOnlyStructureTrait
{
    private array $data = [];

    /**
     * Private constructor. Instantiate this object using self::fromData().
     */
    private function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Magic getter.
     *
     * @return mixed
     */
    public function __get(string $name)
    {
        $this->checkExists($name);

        return $this->data[$name];
    }

    /**
     * Magic isset() support.
     *
     * @return bool
     */
    public function __isset(string $name)
    {
        return isset($this->data[$name]);
    }

    /**
     * Magic setter.
     *
     * @throws \InvalidArgumentException if the property is not found
     * @throws \BadMethodCallException if the property is found
     */
    public function __set(string $name, mixed $value)
    {
        $this->checkExists($name);
        throw new \BadMethodCallException('Property not writable: ' . $name);
    }

    /**
     * Construct from API data.
     */
    public static function fromData(array $data): static
    {
        return new static($data);
    }

    /**
     * Get all properties.
     */
    public function getProperties(): array
    {
        return $this->data;
    }

    /**
     * Gets a single property.
     *
     * @return mixed|null
     *   Returns the property value, or null if $required is false and the property is not set.
     *@throws \InvalidArgumentException if $required is true and the property is not set
     */
    public function getProperty($property, bool $required = true): mixed
    {
        if (! array_key_exists($property, $this->data)) {
            if ($required) {
                throw new \InvalidArgumentException("Property not found: {$property}");
            }
            return null;
        }

        return $this->data[$property];
    }

    /**
     * Check if a property exists.
     *
     * @throws \InvalidArgumentException if the property is not found
     */
    private function checkExists(string $property): void
    {
        if (! array_key_exists($property, $this->data)) {
            throw new \InvalidArgumentException('Property not found: ' . $property);
        }
    }
}
