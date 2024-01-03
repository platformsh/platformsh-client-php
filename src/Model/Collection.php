<?php

namespace Platformsh\Client\Model;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ParseException;
use Platformsh\Client\Exception\ApiResponseException;
use Platformsh\Client\Model\Ref\Resolver;

/**
 * Represents a collection of items.
 *
 * Automatically resolves references when fetching the next or previous page.
 */
class Collection
{
    private $data;
    private $client;
    private $baseUrl;
    private $resolver;

    public function __construct(array $data, ClientInterface $client, $baseUrl)
    {
        $this->data = $data;
        $this->client = $client;
        $this->baseUrl = $baseUrl;
        $this->resolver = new Resolver($client, $baseUrl);
    }

    /**
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Returns the total count of items in a collection (across all pages), if available.
     *
     * @return int|null
     */
    public function getTotalCount()
    {
        return isset($this->data['count']) ? (int) $this->data['count'] : null;
    }

    /**
     * @internal
     * @param array $data
     * @return void
     */
    public function setData(array $data)
    {
        $this->data = $data;
    }

    /**
     * @return bool
     */
    public function hasNextPage()
    {
        return !empty($this->data['_links']['next']['href']);
    }

    /**
     * @return static|null
     */
    public function fetchNextPage()
    {
        if (empty($this->data['_links']['next']['href'])) {
            return null;
        }
        return $this->doFetchPage($this->data['_links']['next']['href']);
    }

    /**
     * @return string|null
     */
    public function getNextPageUrl()
    {
        if (empty($this->data['_links']['next']['href'])) {
            return null;
        }
        return $this->data['_links']['next']['href'];
    }

    /**
     * @return bool
     */
    public function hasPreviousPage()
    {
        return !empty($this->data['_links']['previous']['href']);
    }

    /**
     * @return static|null
     */
    public function fetchPreviousPage()
    {
        if (empty($this->data['_links']['previous']['href'])) {
            return null;
        }
        return $this->doFetchPage($this->data['_links']['previous']['href']);
    }

    /**
     * @return string|null
     */
    public function getPreviousPageUrl()
    {
        if (empty($this->data['_links']['previous']['href'])) {
            return null;
        }
        return $this->data['_links']['previous']['href'];
    }

    private function doFetchPage($url)
    {
        $request = $this->client->createRequest('GET', $url);
        try {
            $response = $this->client->send($request);
            $data = $response->json();
            $data = $this->resolver->resolveReferences($data);

            return new static($data, $this->client, $this->baseUrl);
        } catch (BadResponseException $e) {
            throw ApiResponseException::create($e->getRequest(), $e->getResponse());
        } catch (ParseException $e) {
            throw ApiResponseException::create($request, isset($response) ? $response : null);
        }
    }
}
