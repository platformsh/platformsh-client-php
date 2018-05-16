<?php

namespace Platformsh\Client\Model;

/**
 * A project-level variable.
 *
 * @property-read string $id
 *   The primary ID of the variable. This is the same as the 'name' property.
 * @property-read string $project
 *   The project ID.
 * @property-read string $name
 *   The name of the variable.
 * @property-read string $value
 *   The value of the variable. This is not readable if $is_sensitive is true.
 * @property-read bool   $is_json
 *   Whether the variable's value is a JSON string.
 * @property-read bool $is_sensitive
 *   Whether the variable is sensitive. If so, its value will not be returned
 *   in the API.
 * @property-read bool   $visible_build
 *   Whether the variable should be visible at build time.
 * @property-read bool   $visible_runtime
 *   Whether the variable should be visible at runtime.
 * @property-read string $created_at
 *   The time the variable was created (ISO 8601).
 * @property-read string $updated_at
 *   The time the variable was last updated (ISO 8601).
 */
class ProjectLevelVariable extends ApiResourceBase
{
}
