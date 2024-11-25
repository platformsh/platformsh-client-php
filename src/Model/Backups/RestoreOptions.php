<?php

declare(strict_types=1);

namespace Platformsh\Client\Model\Backups;

class RestoreOptions
{
    private ?string $environmentName = null;

    private ?string $branchFrom = null;

    private ?bool $restoreCode;

    private ?bool $restoreResources;

    private ?string $resourcesInit;

    /**
     * @return RestoreOptions
     */
    public function setEnvironmentName(?string $environmentName): static
    {
        $this->environmentName = $environmentName;
        return $this;
    }

    /**
     * @return RestoreOptions
     */
    public function setBranchFrom(?string $branchFrom): static
    {
        $this->branchFrom = $branchFrom;
        return $this;
    }

    /**
     * @return RestoreOptions
     */
    public function setRestoreCode(?bool $restoreCode): static
    {
        $this->restoreCode = $restoreCode;
        return $this;
    }

    /**
     * @return RestoreOptions
     */
    public function setRestoreResources(?bool $restoreResources): static
    {
        $this->restoreResources = $restoreResources;
        return $this;
    }

    /**
     * @return RestoreOptions
     */
    public function setResourcesInit(?string $init): static
    {
        $this->resourcesInit = $init;
        return $this;
    }

    /**
     * Returns a resource options structure as an associative array.
     */
    public function toArray(): array
    {
        $arr = [];
        if ($this->environmentName !== null) {
            $arr['environment_name'] = $this->environmentName;
        }
        if ($this->branchFrom !== null) {
            $arr['branch_from'] = $this->branchFrom;
        }
        if ($this->restoreCode !== null) {
            $arr['restore_code'] = $this->restoreCode;
        }
        if ($this->restoreResources !== null) {
            $arr['restore_resources'] = $this->restoreResources;
        }
        if ($this->resourcesInit !== null) {
            $arr['resources']['init'] = $this->resourcesInit;
        }
        return $arr;
    }
}
