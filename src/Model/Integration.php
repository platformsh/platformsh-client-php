<?php

namespace Platformsh\Client\Model;

/**
 * A project integration.
 *
 * @property-read string $id
 * @property-read string $type
 */
class Integration extends Resource
{

    /** @var array */
    protected static $required = ['type'];

    /** @var array */
    protected static $types = ['bitbucket', 'hipchat', 'github', 'webhook'];

    /**
     * @inheritdoc
     */
    protected static function checkProperty($property, $value)
    {
        $errors = [];
        if ($property === 'type' && !in_array($value, self::$types)) {
            $errors[] = "Invalid type: '$value'";
        }
        return $errors;
    }
}
