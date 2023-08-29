<?php

namespace Platformsh\Client\Model;

use Platformsh\Client\Exception\ProjectReferenceException;
use Platformsh\Client\Model\CentralizedPermissions\UserExtendedAccess;
use Platformsh\Client\Model\Ref\OrganizationRef;

/**
 * Contains basic information about a project.
 *
 * This is a kind of combination of ProjectRef and ProjectStub as the
 * information may come from either API in order to maintain backwards
 * compatibility.
 */
class BasicProjectInfo
{
    /** @var string */
    public $id;
    /** @var string */
    public $title;
    /** @var string|null */
    public $region;
    /** @var string|null */
    public $subscription_id;
    /** @var OrganizationRef|null */
    public $organization_ref;
    /** @var string|null */
    public $created_at;
    /** @var string|null */
    public $status;
    /** @var string|null */
    public $organization_id;
    /** @var string|null */
    public $owner_id;

    private function __construct($id, $title)
    {
        $this->id = $id;
        $this->title = $title;
    }

    public static function fromStub(ProjectStub $stub)
    {
        $obj = new static($stub->id, $stub->title);
        $obj->subscription_id = $stub->subscription_id;
        $obj->created_at = $stub->created_at;
        $obj->region = $stub->region;
        $obj->organization_ref = $stub->getOrganizationInfo();
        $obj->status = $stub->status;
        $obj->owner_id = $stub->getProperty('owner', false, false) ?: null;
        $obj->organization_id = $stub->getProperty('organization_id', false, false) ?: null;
        return $obj;
    }

    public static function fromExtendedAccess(UserExtendedAccess $extendedAccess)
    {
        if ($extendedAccess->resource_type !== 'project') {
            throw new \InvalidArgumentException('Cannot resolve project information for resource type: ' . $extendedAccess->resource_type);
        }
        $ref = $extendedAccess->getProjectInfo();
        if ($ref === null) {
            throw new ProjectReferenceException($extendedAccess->resource_id);
        }
        $obj = new static($ref->id, $ref->title);
        $obj->subscription_id = $ref->subscription_id;
        $obj->created_at = $ref->created_at;
        $obj->region = $ref->region;
        $obj->status = $ref->status;
        $obj->organization_id = $extendedAccess->organization_id;
        $obj->organization_ref = $extendedAccess->getOrganizationInfo();
        $obj->owner_id = $ref->organization_id;
        return $obj;
    }
}
