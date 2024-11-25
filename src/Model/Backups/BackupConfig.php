<?php

declare(strict_types=1);

namespace Platformsh\Client\Model\Backups;

class BackupConfig
{
    /**
     * @var Policy[]
     */
    private array $policies;

    private int $manualCount;

    /**
     * Private constructor: use self::fromData().
     *
     * @param Policy[] $policies
     */
    private function __construct(array $policies, int $manualCount)
    {
        $this->policies = $policies;
        $this->manualCount = $manualCount;
    }

    /**
     * Instantiates a backup configuration object from config data.
     */
    public static function fromData(array $data): static
    {
        $policies = [];
        foreach ($data['schedule'] ?? [] as $policyData) {
            $policies[] = new Policy($policyData['interval'], $policyData['count']);
        }

        return new static($policies, $data['manual_count'] ?? 1);
    }

    /**
     * Get the configured number of manual backups to keep.
     */
    public function getManualCount(): int
    {
        return $this->manualCount;
    }

    /**
     * Get a list of backup retention policies.
     *
     * @return Policy[]
     */
    public function getPolicies(): array
    {
        return $this->policies;
    }
}
