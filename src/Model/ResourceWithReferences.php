<?php

namespace Platformsh\Client\Model;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use Platformsh\Client\Model\Ref\Resolver;

class ResourceWithReferences extends Resource
{
    protected function setData(array $data)
    {
        // References are resolved upon initialization so that the links are less likely to have expired.
        $data = self::resolveReferences(new Resolver($this->client, $this->baseUrl), $data);
        parent::setData($data);
    }

    /**
     * @param Resolver $resolver
     * @param array $data
     *
     * @return array
     */
    protected static function resolveReferences(Resolver $resolver, array $data)
    {
        if (isset($data['_links'])) {
            try {
                $data = $resolver->resolveReferences($data);
            } catch (\Exception $e) {
                $message = $e->getMessage();
                if ($e instanceof BadResponseException && $e->getResponse()) {
                    $message = \sprintf('status code %d', $e->getResponse()->getStatusCode());
                }
                \trigger_error('Unable to resolve references: ' . $message, E_USER_WARNING);
            }
        }
        return $data;
    }

    public static function wrapCollection(array $data, $baseUrl, ClientInterface $client)
    {
        $data = self::resolveReferences(new Resolver($client, $baseUrl), $data);

        $resources = [];
        foreach ($data['items'] as $item) {
            foreach ($item as $key => $value) {
                // Add user-related references onto the individual item (the rest of $data is discarded).
                if (\in_array($key, ['owner_id', 'user_id']) && isset($data['ref:users'][$value])) {
                    $item['ref:users'][$value] = $data['ref:users'][$value];
                }
                // And organization-related references.
                if (\in_array($key, ['organization_id']) && isset($data['ref:organizations'][$value])) {
                    $item['ref:organizations'][$value] = $data['ref:organizations'][$value];
                }
            }

            $resources[] = new static($item, $baseUrl, $client);
        }

        return $resources;
    }
}
