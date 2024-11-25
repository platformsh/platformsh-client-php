<?php

declare(strict_types=1);

namespace Platformsh\Client\Model;

/**
 * Represents a Platform.sh plan.
 *
 * @property-read string $name  A machine name, e.g. "2xlarge".
 * @property-read string $label A label, e.g. "2X-Large".
 * @property-read Price  $price The plan price
 */
class Plan extends ApiResourceBase
{
    protected static ?string $collectionItemsKey = 'plans';

    public function __get(string $name)
    {
        if ($name === 'price') {
            return Price::fromData($this->data['price']);
        }

        return parent::__get($name);
    }

    public function update(array $values): Result
    {
        throw new \BadMethodCallException('Update is not available for plans');
    }
}
