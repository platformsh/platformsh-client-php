<?php

namespace Platformsh\Client\Model;

use GuzzleHttp\ClientInterface;

class Subscription extends Resource
{

    /**
     * Get the account for the project's owner.
     *
     * @return Account
     */
    public function getOwner()
    {
        $uuid = $this->getProperty('owner');
        $url = $this->makeAbsoluteUrl('/api/users', $this->getProperty('endpoint'));
        return Account::get($uuid, $url, $this->client);
    }

    /**
     * Get the project associated with this subscription.
     *
     * @return Project|false
     */
    public function getProject()
    {
        $url = $this->getProperty('endpoint');
        return Project::get($url, null, $this->client);
    }

    /**
     * @inheritdoc
     */
    public static function wrapCollection(array $data, $baseUrl, ClientInterface $client)
    {
        $data = isset($data['hal:subscriptions']) ? $data['hal:subscriptions'] : [];
        return parent::wrapCollection($data, $baseUrl, $client);
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
