<?php

namespace Platformsh\Client\Model;

/**
 * Represents a source operation on a Platform.sh environment.
 *
 * @see https://api.platform.sh/docs/#tag/Source-Operations
 *
 * @property-read string $app
 * @property-read string $operation
 * @property-read string $command
 */
class SourceOperation extends ApiResourceBase {}
