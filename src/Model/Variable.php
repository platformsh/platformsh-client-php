<?php

namespace Platformsh\Client\Model;

/**
 * An environment variable.
 *
 * @property-read string $id
 * @property-read string $environment
 * @property-read string $name
 * @property-read bool   $is_json
 * @property-read string $created_at
 * @property-read string $updated_at
 * @property-read string $value
 * @property-read string $project
 * @property-read bool   $inherited
 */
class Variable extends Resource
{

}
