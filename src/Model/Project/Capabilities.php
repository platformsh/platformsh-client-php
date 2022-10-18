<?php

namespace Platformsh\Client\Model\Project;

use Platformsh\Client\DataStructure\ReadOnlyStructureTrait;

/**
 * Capabilities represent what features a project has, defined by a billing system.
 *
 * See https://api.platform.sh/docs/#tag/Project/operation/get-projects-capabilities
 *
 * @property-read array $custom_domains
 * @property-read array $integrations
 * @property-read array $metrics
 * @property-read array $outbound_firewall
 * @property-read array $source_operations
 */
class Capabilities
{
    use ReadOnlyStructureTrait;
}
