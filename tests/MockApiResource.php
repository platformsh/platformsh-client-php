<?php

declare(strict_types=1);

namespace Platformsh\Client\Tests;

use Platformsh\Client\Model\ApiResourceBase;

class MockApiResource extends ApiResourceBase
{
    protected static $required = ['testProperty'];

    protected static function checkProperty($property, $value): array
    {
        $errors = [];
        if ($property === 'testProperty' && $value !== '1') {
            $errors[] = "{$property} must be 1";
        }
        return $errors;
    }
}
