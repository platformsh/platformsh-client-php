<?php

namespace Platformsh\Client\Model\Billing;

use GuzzleHttp\ClientInterface;
use Platformsh\Client\Model\ApiResourceBase;

/**
 * Represents a Platform.sh plan record.
 *
 * @property-read int    $id            The ID of the record.
 * @property-read string $owner         The ID of the owner.
 * @property-read int    $subscription_id The ID of the subscription.
 * @property-read string $plan          The machine name of the plan.
 * @property-read string $start         The start date of the record (ISO 8601).
 * @property-read string $end           The end date of the record (ISO 8601).
 * @property-read string $status        The subscription status in this record.
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
