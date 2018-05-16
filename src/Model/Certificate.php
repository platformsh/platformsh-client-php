<?php

namespace Platformsh\Client\Model;

/**
 * A certificate attached to a project.
 *
 * @property-read string   $id
 * @property-read bool     $is_provisioned
 * @property-read string   $certificate
 * @property-read string[] $chain
 * @property-read string[] $domains
 * @property-read array    $issuer
 * @property-read string   $created_at
 * @property-read string   $updated_at
 * @property-read string   $expires_at
 */
class Certificate extends ApiResourceBase
{
    /** @var array */
    protected static $required = ['key', 'certificate'];
}
