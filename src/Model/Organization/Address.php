<?php

namespace Platformsh\Client\Model\Organization;

use GuzzleHttp\ClientInterface;
use Platformsh\Client\Model\Resource;
use Platformsh\Client\Model\Result;

/**
 * @property-read string $country
 * @property-read string $name_line
 * @property-read string $premise
 * @property-read string $sub_premise
 * @property-read string $thoroughfare
 * @property-read string $administrative_area
 * @property-read string $sub_administrative_area
 * @property-read string $locality
 * @property-read string $dependent_locality
 * @property-read string $postal_code
 */
class Address extends Resource
{
    /**
     * Updates the address.
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
        throw new \BadMethodCallException('An address cannot be explicitly created');
    }

    public function getLink($rel, $absolute = true)
    {
        if (!$this->hasLink($rel)) {
            // The address API does not expose HAL links yet.
            if ($rel === 'self') {
                return $this->baseUrl;
            }
        }
        return parent::getLink($rel, $absolute);
    }
}
