<?php

namespace Platformsh\Client\Model\Backups;

use Platformsh\Client\DataStructure\WriteOnceStructureTrait;

class RestoreOptions
{
    use WriteOnceStructureTrait;

    /** @var string|null */
    private $environment_name;

    /** @var string|null */
    private $branch_from;

    /** @var bool|null */
    private $restore_code;

    /** @var array{init: string}|null */
    private $resources;
}
