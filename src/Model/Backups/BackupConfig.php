<?php

namespace Platformsh\Client\Model\Backups;

class BackupConfig
{
    /** @var Policy[] */
    private $policies;

    /** @var int */
    private $manualCount;

    /**
     * Private constructor: use self::fromData().
     *
     * @param Policy[] $policies
     * @param int      $manualCount
     */
    private function __construct(array $policies, $manualCount)
    {
        $this->policies = $policies;
        $this->manualCount = $manualCount;
    }

    /**
     * Instantiates a backup configuration object from config data.
     *
     * @param array $data
     *
     * @return static
     */
    public static function fromData(array $data)
    {
        $policies = [];
        foreach ($data['schedule'] ?? [] as $policyData) {
            $policies[] = new Policy($policyData['interval'], $policyData['count']);
        }

        return new static($policies, isset($data['manual_count']) ? $data['manual_count'] : 1);
    }

    /**
     * Get the configured number of manual backups to keep.
     *
     * @return int
     */
    public function getManualCount()
    {
        return $this->manualCount;
    }

    /**
     * Get a list of backup retention policies.
     *
     * @return Policy[]
     */
    public function getPolicies()
    {
        return $this->policies;
    }
}
