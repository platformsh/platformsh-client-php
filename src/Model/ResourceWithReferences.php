<?php

declare(strict_types=1);

namespace Platformsh\Client\Model;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use Platformsh\Client\Model\Ref\Resolver;

class ResourceWithReferences extends ApiResourceBase
{
    protected static ?string $collectionItemsKey = 'items';

    public static function wrapCollection(array|Collection $data, string $baseUrl, ClientInterface $client): array
    {
        if ($data instanceof Collection) {
            $parent = $data;
            $data = $data->getData();
        } else {
            $parent = new Collection($data, $client, $baseUrl);
        }
        $data = self::resolveReferences(new Resolver($client, $baseUrl), $data);
        $parent->setData($data);

        // Add referenced information for the whole collection onto individual
        // resources, based on this map of resource keys to reference API sets.
        $map = [
            'project_id' => 'projects',
            'owner_id' => 'users',
            'user_id' => 'users',
            'organization_id' => 'organizations',
            'team_id' => 'teams',
        ];

        $resources = [];
        foreach ($data[static::$collectionItemsKey] as $item) {
            if (isset($item['resource_type'], $item['resource_id'])) {
                $set = $item['resource_type'] . 's';
                if (isset($data['ref:' . $set][$item['resource_id']])) {
                    $item['ref:' . $set][$item['resource_id']] = $data['ref:' . $set][$item['resource_id']];
                }
            }
            foreach ($map as $key => $set) {
                if (isset($item[$key]) && isset($data['ref:' . $set][$item[$key]])) {
                    $item['ref:' . $set][$item[$key]] = $data['ref:' . $set][$item[$key]];
                }
            }
            $resource = new static($item, $baseUrl, $client);
            $resource->setParentCollection($parent);
            $resources[] = $resource;
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
     * @return array{items: static[], next: ?string, previous: ?string}
     */
    public static function getPagedCollection(string $url, ClientInterface $client, array $options = []): array
    {
        $request = new Request('get', $url);
        $data = static::send($request, $client, $options);
        $items = static::wrapCollection($data, $url, $client);

        $ret = [
            'items' => $items,
            'next' => null,
            'previous' => null,
        ];

        $base = new Uri($url);
        foreach (['previous', 'next'] as $rel) {
            if (isset($data['_links'][$rel]['href'])) {
                $next = new Uri($data['_links'][$rel]['href']);
                $resolved = $base->withPath($next->getPath())->withQuery($next->getQuery());
                $ret[$rel] = $resolved->__toString();
            }
        }

        return $ret;
    }

    protected function setData(array $data): void
    {
        // References are resolved upon initialization so that the links are less likely to have expired.
        $data = self::resolveReferences(new Resolver($this->client, $this->baseUrl), $data);
        parent::setData($data);
    }

    protected static function resolveReferences(Resolver $resolver, array $data): array
    {
        if (isset($data['_links'])) {
            try {
                $data = $resolver->resolveReferences($data);
            } catch (\Exception $e) {
                $message = $e->getMessage();
                if ($e instanceof BadResponseException) {
                    $message = \sprintf('status code %d', $e->getResponse()->getStatusCode());
                }
                \trigger_error('Unable to resolve references: ' . $message, E_USER_WARNING);
            }
        }
        return $data;
    }
}
