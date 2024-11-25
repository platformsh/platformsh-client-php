<?php

declare(strict_types=1);

namespace Platformsh\Client\Model\Organization;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Utils;
use Platformsh\Client\Model\ApiResourceBase;
use Platformsh\Client\Model\Result;

/**
 * @property-read string $company_name
 * @property-read string $billing_contact
 * @property-read string $security_contact
 * @property-read string $vat_number
 * @property-read string $currency
 * @property-read string $current_trial
 */
class Profile extends ApiResourceBase
{
    /**
     * Updates the profile.
     *
     * This updates the resource's internal data with the API response.
     */
    public function update(array $values): Result
    {
        // @todo use getLink('#edit') when it is available
        $url = $this->getUri();
        $options = [];
        if (! empty($values)) {
            $options['json'] = $values;
        }
        $response = $this->client->patch($url, $options);
        $data = Utils::jsonDecode((string) $response->getBody(), true);
        $this->setData($data);

        return new Result($data, $this->baseUrl, $this->client, static::class);
    }

    public static function create(array $body, string $collectionUrl, ClientInterface $client)
    {
        throw new \BadMethodCallException('A profile cannot be explicitly created');
    }
}
