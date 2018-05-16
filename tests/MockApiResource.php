<?php

namespace Platformsh\Client\Tests;

use Platformsh\Client\Model\ApiResourceBase;

class MockApiResource extends ApiResourceBase
{

    protected static $required = ['testProperty'];

    protected static function checkProperty($property, $value)
    {
        $errors = [];
        if ($property === 'testProperty' && $value != '1') {
            $errors[] = "$property must be 1";
        }
        return $errors;
    }
}
