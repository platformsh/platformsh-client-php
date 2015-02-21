<?php

namespace Platformsh\Client\Model;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;

class Resource implements ResourceInterface
{

    /**
     * @var ClientInterface
     */
    protected $client;

    /** @var array */
    protected $data;

    /**
     * @param array           $data
     * @param ClientInterface $client
     */
    public function __construct(array $data = [], ClientInterface $client = null)
    {
        $this->data = $data;
        $this->client = $client ?: new Client();
    }

    /**
     * @param string          $id
     * @param string          $collectionUrl
     * @param ClientInterface $client
     *
     * @return static
     */
    public static function get($id, $collectionUrl, ClientInterface $client)
    {
        $data = $client->get($collectionUrl . '/' . $id)->json();
        return static::wrap($data, $client);
    }

    /**
     * @param string          $url
     * @param array           $options
     * @param ClientInterface $client
     *
     * @return static
     */
    public static function getCollection($url, array $options = [], ClientInterface $client)
    {
        $data = $client->get($url, $options)->json();
        return static::wrapCollection($data, $client);
    }

    /**
     * @todo automatically wrap Guzzle responses
     *
     * @param array $data
     * @param ClientInterface $client
     *
     * @return static
     */
    public static function wrap(array $data, ClientInterface $client)
    {
        return new static($data, $client);
    }

    /**
     * @todo automatically wrap Guzzle responses
     *
     * @param array $data
     * @param ClientInterface $client
     *
     * @return static[]
     */
    public static function wrapCollection(array $data, ClientInterface $client)
    {
        return array_map(function ($item) use ($client) {
            return new static($item, $client);
        }, $data);
    }

    /**
     * Execute an operation on the resource.
     *
     * This updates the internal 'data' property with the API response.
     *
     * @param string $op
     * @param string $method
     * @param array $body
     *
     * @return array
     */
    protected function runOperation($op, $method = 'post', array $body = [])
    {
        if (!$this->operationAvailable($op)) {
            throw new \RuntimeException("Operation not available: $op");
        }
        $options = [];
        if ($body) {
            $options['body'] = json_encode($body);
        }
        $request = $this->client
          ->createRequest($method, $this->getLink("#$op"), $options);
        $response = $this->client->send($request);
        return (array) $response->json();
    }

    /**
     * @throws \Exception
     *
     * @return string
     */
    public function getUri()
    {
        if (!isset($this->data['_links']['self']['href'])) {
            throw new \Exception('URI not found');
        }
        return $this->data['_links']['self']['href'];
    }

    public function getData()
    {
        return $this->data;
    }

    public function refresh(array $options = [])
    {
        $response = $this->client->get($this->getLink('self'), $options);
        $this->data = $response->json();
    }

    protected function operationAvailable($op)
    {
        return (bool) $this->getLink("#$op", false);
    }

    public function getLink($rel, $required = true)
    {
        if (!isset($this->data['_links'][$rel]['href'])) {
            if ($required) {
                throw new \InvalidArgumentException("Link not found: $rel");
            }
            return false;
        }

        return $this->data['_links'][$rel]['href'];
    }
}
