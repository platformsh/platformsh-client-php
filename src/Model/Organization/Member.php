<?php

namespace Platformsh\Client\Model\Organization;

use GuzzleHttp\ClientInterface;
use Platformsh\Client\Model\Resource;

/**
 * @property-read string $id
 * @property-read string $organization_id
 * @property-read string $user_id
 * @property-read string[] $permissions
 * @property-read bool $owner
 * @property-read string $created_at
 * @property-read string $updated_at
 */
class Member extends Resource
{
    public static function wrapCollection(array $data, $baseUrl, ClientInterface $client)
    {
        $data = isset($data['items']) ? $data['items'] : [];
        return parent::wrapCollection($data, $baseUrl, $client);
    }
}
