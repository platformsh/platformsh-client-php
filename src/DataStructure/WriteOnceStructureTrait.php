<?php

namespace Platformsh\Client\DataStructure;

trait WriteOnceStructureTrait
{
    /**
     * Instantiates the class from an associative array.
     *
     * @param array $options
     *
     * @return self
     */
    public static function fromArray(array $options)
    {
        $obj = new self();
        foreach ($options as $key => $value) {
            if (\property_exists($obj, $key)) {
                $obj->$key = $value;
            } else {
                throw new \InvalidArgumentException('Unknown property: ' . $key);
            }
        }
        return $obj;
    }

    /**
     * Returns the class's properties and values as an associative array.
     *
     * @param bool $skipNull
     *
     * @return array
     */
    public function toArray($skipNull = true) {
        $arr = [];
        foreach ($this as $key => $value) {
            if (!$skipNull || $value !== null) {
                $arr[$key] = $value;
            }
        }
        return $arr;
    }
}
