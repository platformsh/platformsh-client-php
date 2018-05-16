<?php

namespace Platformsh\Client\Model;

/**
 * An environment-level variable.
 *
 * @property-read string $id
 *   The primary ID of the variable. This is the same as the 'name' property.
 * @property-read string $project
 *   The project ID.
 * @property-read string $environment
 *   The environment ID.
 * @property-read string $name
 *   The name of the variable.
 * @property-read string $value
 *   The value of the variable. This is not readable if $is_sensitive is true.
 * @property-read bool $is_sensitive
 *   Whether the variable is sensitive. If so, its value will not be returned
 *   in the API.
 * @property-read bool   $is_enabled
 *   Whether the variable is enabled.
 * @property-read bool   $is_json
 *   Whether the variable's value is a JSON string.
 * @property-read string $created_at
 *   The time the variable was created (ISO 8601).
 * @property-read string $updated_at
 *   The time the variable was last updated (ISO 8601).
 * @property-read bool   $inherited
 *   Whether the variable was inherited from a parent environment.
 * @property-read bool   $is_inheritable
 *   Whether the variable is allowed to be inherited by a child environment.
 */
class Variable extends ApiResourceBase
{

    /**
     * Disable the variable.
     *
     * This is only useful if the variable is both inherited and enabled.
     * Non-inherited variables can be deleted.
     *
     * @return Result
     */
    public function disable()
    {
        if (!$this->getProperty('is_enabled')) {
            return new Result([], $this->baseUrl, $this->client, get_called_class());
        }

        return $this->update(['is_enabled' => false]);
    }
}
