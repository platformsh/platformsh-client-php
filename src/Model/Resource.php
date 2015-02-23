<?php

namespace Platformsh\Client\Model;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;

class Resource
{

    /** @var ClientInterface */
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
     * Ensure that this is a full representation of the resource (not a stub).
     */
    public function ensureFull()
    {
        if (empty($this->data['_full'])) {
            $this->refresh();
        }
    }

    /**
     * Get a resource by its ID.
     *
     * @param string          $id
     * @param string          $collectionUrl
     * @param ClientInterface $client
     *
     * @return static|false
     */
    public static function get($id, $collectionUrl, ClientInterface $client)
    {
        try {
            $response = $client->get(rtrim($collectionUrl, '/') . '/' . $id);
            $data = $response->json();
            $data['_full'] = true;
            $data['_url'] = $response->getEffectiveUrl();

            return static::wrap($data, $client);
        } catch (BadResponseException $e) {
            $response = $e->getResponse();
            if ($response && $response->getStatusCode() === 404) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Create a resource.
     *
     * @param array           $body
     * @param string          $collectionUrl
     * @param ClientInterface $client
     *
     * @return static
     */
    public static function create(array $body, $collectionUrl, ClientInterface $client)
    {
        $response = $client->post($collectionUrl, ['body' => $body]);
        $data = (array) $response->json();
        $data['_full'] = true;

        return static::wrap($data, $client);
    }

    /**
     * Get a collection of resources.
     *
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
     * Create a resource instance from JSON data.
     *
     * @param array           $data
     * @param ClientInterface $client
     *
     * @return static
     */
    public static function wrap(array $data, ClientInterface $client)
    {
        return new static($data, $client);
    }

    /**
     * Create an array of resource instances from a collection's JSON data.
     *
     * @param array           $data
     * @param ClientInterface $client
     *
     * @return static[]
     */
    public static function wrapCollection(array $data, ClientInterface $client)
    {
        return array_map(
          function ($item) use ($client) {
              return new static($item, $client);
          },
          $data
        );
    }

    /**
     * Execute an operation on the resource.
     *
     * @param string $op
     * @param string $method
     * @param array  $body
     *
     * @return array
     */
    protected function runOperation($op, $method = 'post', array $body = [])
    {
        if (!$this->operationAvailable($op)) {
            throw new \RuntimeException("Operation not available: $op");
        }
        $options = [];
        if (!empty($body)) {
            $options['body'] = json_encode($body);
        }
        $request = $this->client
          ->createRequest($method, $this->getLink("#$op"), $options);
        $response = $this->client->send($request);

        return (array) $response->json();
    }

    /**
     * Run a long-running operation.
     *
     * @param string $op
     * @param string $method
     * @param array  $body
     *
     * @throws \Exception
     *
     * @return Activity
     */
    protected function runLongOperation($op, $method = 'post', array $body = [])
    {
        $data = $this->runOperation($op, $method, $body);
        if (!isset($data['_embedded']['activities'][0])) {
            throw new \Exception('Expected activity not found');
        }

        return Activity::wrap($data['_embedded']['activities'][0], $this->client);
    }

    /**
     * Get a property of the resource.
     *
     * @param string $property
     *
     * @throws \InvalidArgumentException
     *
     * @return mixed
     */
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
     * Update the resource.
     *
     * @param array $values
     */
    public function update(array $values = [])
    {
        $this->runOperation('edit', 'patch', $values);
    }

    /**
     * Get the resource's URI.
     *
     * @return string
     */
    protected function getUri()
    {
        return $this->getLink('self');
    }

    /**
     * Get all of the resource's data (as returned by the API in JSON).
     *
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Refresh the resource.
     *
     * @param array $options
     */
    public function refresh(array $options = [])
    {
        $response = $this->client->get($this->getLink('self'), $options);
        $this->data = (array) $response->json();
        $this->data['_url'] = $response->getEffectiveUrl();
        $this->data['_full'] = true;
    }

    /**
     * Check whether an operation is available on the resource.
     *
     * @param string $op
     *
     * @return bool
     */
    protected function operationAvailable($op)
    {
        return isset($this->data['_links']["#$op"]['href']);
    }

    /**
     * Get a link for a given resource relation.
     *
     * @param string $rel
     *
     * @return string
     */
    public function getLink($rel)
    {
        if (!isset($this->data['_links'][$rel]['href'])) {
            throw new \InvalidArgumentException("Link not found: $rel");
        }

        return $this->data['_links'][$rel]['href'];
    }
}
