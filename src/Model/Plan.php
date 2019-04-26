<?php

namespace Platformsh\Client\Model;

use GuzzleHttp\ClientInterface;

/**
 * Represents a Platform.sh plan.
 *
 * @property-read string $name  A machine name, e.g. "2xlarge".
 * @property-read string $label A label, e.g. "2X-Large".
 * @property-read Price  $price The plan price
 */
class Plan extends ApiResourceBase
{
    /**
     * {@inheritdoc}
     */
    public function __get($name)
    {
        if ($name === 'price') {
            return Price::fromData($this->data['price']);
        }

        return parent::__get($name);
    }

    /**
     * @inheritdoc
     */
    protected function setData(array $data)
    {
        $data = isset($data['plans'][0]) ? $data['plans'][0] : $data;
        $this->data = $data;
    }

    /**
     * @inheritdoc
     */
    public static function wrapCollection(array $data, $baseUrl, ClientInterface $client)
    {
        $data = isset($data['plans']) ? $data['plans'] : [];

        return parent::wrapCollection($data, $baseUrl, $client);
    }

    /**
     * {@inheritdoc}
     */
    public function update(array $values)
    {
        throw new \BadMethodCallException('Update is not available for plans');
    }
}
