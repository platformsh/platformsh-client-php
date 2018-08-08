<?php

namespace Platformsh\Client\Model;

use GuzzleHttp\ClientInterface;

/**
 * Represents a Platform.sh plan record.
 *
 * @property-read int    $id
 * @property-read string $owner
 * @property-read int    $subscription_id
 * @property-read string $sku
 * @property-read string $plan
 * @property-read string $start
 * @property-read string $end
 * @property-read string $status
 */
class PlanRecord extends ApiResourceBase
{

    const COLLECTION_NAME = 'plan';

    /**
     * @inheritdoc
     */
    public static function wrapCollection(array $data, $baseUrl, ClientInterface $client)
    {
        $data = isset($data[self::COLLECTION_NAME]) ? $data[self::COLLECTION_NAME] : [];
        return parent::wrapCollection($data, $baseUrl, $client);
    }

}
