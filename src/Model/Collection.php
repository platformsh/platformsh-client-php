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
    private $resolver;

    public function __construct(array $data, ClientInterface $client)
    {
        $this->data = $data;
        $this->client = $client;
        $this->resolver = new Resolver($client, $data['_links']['self']['href']);
    }

    /**
     * @return array
     */
    public function getData()
    {
        return $this->data;
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

    private function doFetchPage($url)
    {
        $request = $this->client->createRequest('GET', $url);
        try {
            $response = $this->client->send($request);
            $data = $response->json();
            $data = $this->resolver->resolveReferences($data);

            return new static($data, $this->client);
        } catch (BadResponseException $e) {
            throw ApiResponseException::create($e->getRequest(), $e->getResponse());
        } catch (ParseException $e) {
            throw ApiResponseException::create($request, isset($response) ? $response : null);
        }
    }
}
