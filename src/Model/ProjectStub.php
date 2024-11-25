<?php

declare(strict_types=1);

namespace Platformsh\Client\Model;

use Platformsh\Client\Model\Ref\OrganizationRef;

/**
 * Stub (meta) information about a Platform.sh project obtained from the /me API.
 *
 * @property-read string $id
 * @property-read string $title
 * @property-read string $created_at
 * @property-read string $updated_at
 * @property-read string $endpoint
 * @property-read string $subscription_id
 * @property-read string $region
 * @property-read string $region_label
 * @property-read string $status
 */
class ProjectStub extends ResourceWithReferences
{
    protected static ?string $collectionItemsKey = 'projects';

    /**
     * Returns the full project resource for this stub.
     */
    public function getProject(): Project
    {
        $project = Project::get($this->endpoint, '', $this->client);
        if ($project === false) {
            throw new \RuntimeException('Project not found: ' . $this->endpoint);
        }
        return $project;
    }

    /**
     * Returns detailed information about the project's organization, if known.
     */
    public function getOrganizationInfo(): ?OrganizationRef
    {
        if (isset($this->data['organization_id']) && isset($this->data['ref:organizations'][$this->data['organization_id']])) {
            return $this->data['ref:organizations'][$this->data['organization_id']];
        }
        return null;
    }
}
