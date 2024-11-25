<?php

declare(strict_types=1);

namespace Platformsh\Client\Model\Organization;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Utils;
use Platformsh\Client\Model\ApiResourceBase;
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
class Address extends ApiResourceBase
{
    /**
     * Updates the address.
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
        $data = Utils::jsonDecode($response->getBody()->__toString(), true);
        $this->setData($data);

        return new Result($data, $this->baseUrl, $this->client, static::class);
    }

    public static function create(array $body, string $collectionUrl, ClientInterface $client): Result
    {
        throw new \BadMethodCallException('An address cannot be explicitly created');
    }

    public function getLink(string $rel, bool $absolute = true): string
    {
        if (! $this->hasLink($rel)) {
            // The address API does not expose HAL links yet.
            if ($rel === 'self') {
                return $this->baseUrl;
            }
        }
        return parent::getLink($rel, $absolute);
    }
}
