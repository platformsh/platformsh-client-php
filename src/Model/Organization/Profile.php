<?php

namespace Platformsh\Client\Model\Organization;

use GuzzleHttp\ClientInterface;
use Platformsh\Client\Model\Resource;
use Platformsh\Client\Model\Result;

/**
 * @property-read string $billing_contact
 * @property-read string $security_contact
 * @property-read string $vat_number
 * @property-read string $currency
 * @property-read string $current_trial
 */
class Profile extends Resource
{
    /**
     * Updates the profile.
     *
     * This updates the resource's internal data with the API response.
     *
     * @param array $values
     *
     * @return Result
     */
    public function update(array $values)
    {
        // @todo use getLink('#edit') when it is available
        $url = $this->getUri();
        $options = [];
        if (!empty($values)) {
            $options['json'] = $values;
        }
        $response = $this->client->patch($url, $options);
        $data = $response->json();
        $this->setData($data);

        return new Result($data, $this->baseUrl, $this->client, get_called_class());
    }

    public static function create(array $body, $collectionUrl, ClientInterface $client)
    {
        throw new \BadMethodCallException('A profile cannot be explicitly created');
    }
}
