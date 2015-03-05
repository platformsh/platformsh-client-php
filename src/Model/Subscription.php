<?php

namespace Platformsh\Client\Model;

use GuzzleHttp\ClientInterface;

class Subscription extends Resource
{

    /**
     * @inheritdoc
     */
    public static function wrapCollection(array $data, ClientInterface $client)
    {
        $data = isset($data['hal:subscriptions']) ? $data['hal:subscriptions'] : [];
        return parent::wrapCollection($data, $client);
    }

    /**
     * @inheritdoc
     */
    public function operationAvailable($op)
    {
        if ($op === 'edit') {
            return true;
        }
        return parent::operationAvailable($op);
    }

    /**
     * @inheritdoc
     */
    public function getLink($rel, $absolute = false)
    {
        if ($rel === '#edit') {
            return $this->getUri($absolute);
        }
        return parent::getLink($rel, $absolute);
    }
}
