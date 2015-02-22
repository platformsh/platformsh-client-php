<?php

namespace Platformsh\Client\Model;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;

class Resource implements ResourceInterface
{

    /**
     * @var ClientInterface
     */
    protected $client;

    /** @var array */
    protected $data;

    /** @var int */
    protected $fetched;

    /**
     * @param array           $data
     * @param ClientInterface $client
     */
    public function __construct(array $data = [], ClientInterface $client = null)
    {
        $this->data = $data;
        $this->fetched = time();
        $this->client = $client ?: new Client();
    }

    public function ensureFull()
    {
        if (empty($this->data['_full'])) {
            $this->refresh();
        }
    }

    /**
     * @param string          $id
     * @param string          $collectionUrl
     * @param ClientInterface $client
     *
     * @return static|false
     */
    public static function get($id, $collectionUrl, ClientInterface $client)
    {
        $url = rtrim($collectionUrl, '/') . '/' . $id;
        try {
            $data = $client->get($url)->json();
            $data['_full'] = true;
            $data['_url'] = $url;
            return static::wrap($data, $client);
        }
        catch (BadResponseException $e) {
            $response = $e->getResponse();
            if ($response && $response->getStatusCode() === 404) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * @param string          $url
     * @param array           $options
     * @param ClientInterface $client
     *
     * @return static[]
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
     * @param string $op
     * @param string $method
     * @param array $body
     *
     * @return \GuzzleHttp\Message\ResponseInterface
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
        return $this->client->send($request);
    }

    /**
     * Run a long-running operation.
     *
     * @param string $op
     * @param string $method
     * @param array $body
     *
     * @throws \Exception
     *
     * @return Activity
     */
    protected function runLongOperation($op, $method = 'post', array $body = [])
    {
        $response = $this->runOperation($op, $method, $body);
        $data = $response->json();
        if (!isset($data['_embedded']['activities'][0])) {
            throw new \Exception('Expected activity not found');
        }
        return Activity::wrap($data['_embedded']['activities'][0], $this->client);
    }

    public function getProperty($property)
    {
        if ($property[0] === '_' || !array_key_exists($property, $this->data)) {
            throw new \InvalidArgumentException("Property not found: $property");
        }
        return $this->data[$property];
    }

    /**
     * Delete the resource.
     *
     * @return array
     */
    public function delete()
    {
        return $this->client->delete($this->getUri())->json();
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
        $this->data['_full'] = true;
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
