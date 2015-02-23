<?php

namespace Platformsh\Client\Model;

class Integration extends Resource
{

    /** @var array */
    protected static $required = ['type'];

    /** @var array */
    protected static $types = ['hipchat', 'github', 'webhook'];

    /**
     * @inheritdoc
     */
    public static function check(array $data)
    {
        $errors = parent::check($data);
        if (isset($data['type']) && !in_array($data['type'], self::$types)) {
            $errors[] = "Invalid type: '{$data['type']}'";
        }
        // @todo check other properties
        return $errors;
    }
}
