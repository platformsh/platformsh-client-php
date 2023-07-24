<?php

namespace Platformsh\Client\Model;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use Platformsh\Client\Model\Ref\Resolver;

class ResourceWithReferences extends ApiResourceBase
{
    protected static $collectionItemsKey = 'items';

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
        foreach ($data[static::$collectionItemsKey] as $item) {
            foreach ($item as $key => $value) {
                // Add user-related references onto the individual item (the rest of $data is discarded).
                if (\in_array($key, ['owner_id', 'user_id']) && isset($data['ref:users'][$value])) {
                    $item['ref:users'][$value] = $data['ref:users'][$value];
                }
                // And organization-related references.
                if ($key === 'organization_id' && isset($data['ref:organizations'][$value])) {
                    $item['ref:organizations'][$value] = $data['ref:organizations'][$value];
                }
                // And project-related references.
                if ($key === 'project_id' && isset($data['ref:projects'][$value])) {
                    $item['ref:projects'][$value] = $data['ref:projects'][$value];
                }
                // And team-related references.
                if ($key === 'team_id' && isset($data['ref:teams'][$value])) {
                    $item['ref:teams'][$value] = $data['ref:teams'][$value];
                }
            }

            $resources[] = new static($item, $baseUrl, $client);
        }

        return $resources;
    }

    /**
     * Returns a paginated list of resources.
     *
     * This is the equivalent of getCollection() with pagination logic.
     *
     * If 'items' is non-empty and if a non-null 'next' URL is returned, this
     * call may be repeated with the new URL to fetch the next page.
     *
     * Use $options['query']['page'] to specify a page number explicitly.
     *
     * @param string $url
     * @param ClientInterface $client
     * @param array $options
     *
     * @return array{'items': static[], 'next': ?string}
     */
    public static function getPagedCollection($url, ClientInterface $client, array $options = [])
    {
        $request = new Request('get', $url, $options);
        $data = static::send($request, $client, $options);
        $items = static::wrapCollection($data, $url, $client);

        $nextUrl = null;
        if (isset($data['_links']['next']['href'])) {
            $linkUri = Utils::uriFor($data['_links']['next']['href']);
            $nextUrl = Utils::uriFor($url)->withPath($linkUri->getPath())->withQuery($linkUri->getQuery())->__toString();
        }

        return ['items' => $items, 'next' => $nextUrl];
    }
}
