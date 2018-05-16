<?php

namespace Platformsh\Client\Model;

/**
 * A route on a Platform.sh project.
 *
 * @property-read string      $id
 * @property-read string      $project
 * @property-read string      $environment
 * @property-read array       $route
 * @property-read array|null  $cache
 * @property-read array|null  $ssi
 * @property-read string|null $upstream
 * @property-read string|null $to
 * @property-read string      $type
 */
class Route extends ApiResourceBase
{

}
