<?php

namespace Platformsh\Client\Model;

class Integration extends Resource
{

    /** @var array */
    public static $types = ['hipchat', 'github', 'webhook'];

    /**
     * @inheritdoc
     */
    public static function check(array $data)
    {
        $errors = parent::check($data);
        if (!in_array($data['type'], self::$types)) {
            $errors[] = "Invalid integration type: '{$data['type']}'";
        }
        // @todo check other properties
        return $errors;
    }
}
