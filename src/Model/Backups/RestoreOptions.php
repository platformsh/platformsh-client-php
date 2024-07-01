<?php

namespace Platformsh\Client\Model\Backups;

class RestoreOptions
{
    /** @var string|null */
    private $environmentName;

    /** @var string|null */
    private $branchFrom;

    /** @var bool|null */
    private $restoreCode;

    /** @var bool|null */
    private $restoreResources;

    /** @var string|null */
    private $resourcesInit;

    /**
     * @param string|null $environmentName
     * @return RestoreOptions
     */
    public function setEnvironmentName($environmentName)
    {
        $this->environmentName = $environmentName;
        return $this;
    }

    /**
     * @param string|null $branchFrom
     * @return RestoreOptions
     */
    public function setBranchFrom($branchFrom)
    {
        $this->branchFrom = $branchFrom;
        return $this;
    }

    /**
     * @param bool|null $restoreCode
     * @return RestoreOptions
     */
    public function setRestoreCode($restoreCode)
    {
        $this->restoreCode = $restoreCode;
        return $this;
    }

    /**
     * @param bool|null $restoreResources
     * @return RestoreOptions
     */
    public function setRestoreResources($restoreResources)
    {
        $this->restoreResources = $restoreResources;
        return $this;
    }

    /**
     * @param string|null $init
     * @return RestoreOptions
     */
    public function setResourcesInit($init)
    {
        $this->resourcesInit = $init;
        return $this;
    }

    /**
     * Returns a resource options structure as an associative array.
     *
     * @return array
     */
    public function toArray()
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
