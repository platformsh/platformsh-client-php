<?php

declare(strict_types=1);

namespace Platformsh\Client\Model\Deployment;

use Platformsh\Client\DataStructure\ReadOnlyStructureTrait;

/**
 * An application.
 *
 * @property-read string      $name
 * @property-read string      $type
 *
 * @property-read array       $access
 * @property-read int         $disk
 * @property-read array       $mounts
 * @property-read array       $preflight
 * @property-read array       $relationships
 * @property-read array       $runtime
 * @property-read string      $size
 * @property-read string|null $timezone
 * @property-read array       $variables
 */
abstract class AppBase
{
    use ReadOnlyStructureTrait;

    /**
     * Returns runtime operations defined for the app, keyed by name.
     *
     * @return array<string, RuntimeOperation>
     */
    public function getRuntimeOperations(): array
    {
        return array_map(function (array $data) {
            return RuntimeOperation::fromData($data);
        }, $this->data['operations']);
    }

    /**
     * Returns a single runtime operation.
     *
     *@throws \InvalidArgumentException if not found
     */
    public function getRuntimeOperation(string $name): RuntimeOperation
    {
        if (! isset($this->data['operations'][$name])) {
            throw new \InvalidArgumentException(sprintf('Operation not found: %s', $name));
        }

        return RuntimeOperation::fromData($this->data['operations'][$name]);
    }
}
