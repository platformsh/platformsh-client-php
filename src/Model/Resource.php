<?php

namespace Platformsh\Client\Model;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;

class Resource implements \ArrayAccess
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
     * @inheritdoc
     */
    public function offsetExists($offset) {
        return $this->propertyExists($offset);
    }

    /**
     * @inheritdoc
     */
    public function offsetGet($offset) {
        return $this->getProperty($offset);
    }

    /**
     * @inheritdoc
     *
     * @throws \BadMethodCallException
     */
    public function offsetSet($offset, $value) {
        throw new \BadMethodCallException('Properties are read-only');
    }

    /**
     * @inheritdoc
     *
     * @throws \BadMethodCallException
     */
    public function offsetUnset($offset) {
        throw new \BadMethodCallException('Properties are read-only');
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
        if ($errors = static::check($body)) {
            $message = "Cannot create resource due to validation error(s): " . implode('; ', $errors);
            throw new \InvalidArgumentException($message);
        }

        $response = $client->post($collectionUrl, ['body' => $body]);
        $data = (array) $response->json();
        $data['_full'] = true;

        return static::wrap($data, $client);
    }

    /**
     * Validate a new resource.
     *
     * @param array $data
     *
     * @return string[] An array of validation errors.
     */
    public static function check(array $data)
    {
        return [];
    }

    /**
     * Get a collection of resources.
     *
     * @param string          $url
     * @param int             $limit
     * @param array           $options
     * @param ClientInterface $client
     *
     * @return static[]
     */
    public static function getCollection($url, $limit = 0, array $options = [], ClientInterface $client)
    {
        if ($limit) {
            // @todo uncomment this when the API implements a 'count' parameter
            // $options['query']['count'] = $limit;
        }
        $data = $client->get($url, $options)->json();

        // @todo remove this when the API implements a 'count' parameter
        if ($limit) {
            $data = array_slice($data, 0, $limit);
        }

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
     * Check whether a property exists in the resource.
     *
     * @param string $property
     *
     * @return bool
     */
    public function propertyExists($property)
    {
        return $property[0] !== '_' && array_key_exists($property, $this->data);
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
        if (!$this->offsetExists($property)) {
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
     * This updates the resource's internal data with the API response.
     *
     * @param array $values
     */
    public function update(array $values)
    {
        $options = ['body' => $values];
        $response = $this->client->patch($this->getUri(), $options);
        $this->data = (array) $response->json();
        $this->data['_url'] = $response->getEffectiveUrl();
        $this->data['_full'] = true;
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
     * Refresh the resource.
     *
     * @param array $options
     */
    public function refresh(array $options = [])
    {
        $response = $this->client->get($this->getUri(), $options);
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
    public function operationAvailable($op)
    {
        return isset($this->data['_links']["#$op"]['href']);
    }

    /**
     * Check whether the resource has a link.
     *
     * @param $rel
     *
     * @return bool
     */
    public function hasLink($rel)
    {
        return isset($this->data['_links'][$rel]['href']);
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
        if (!$this->hasLink($rel)) {
            throw new \InvalidArgumentException("Link not found: $rel");
        }

        return $this->data['_links'][$rel]['href'];
    }

    /**
     * Get a list of this resource's property names.
     *
     * @return string[]
     */
    public function getPropertyNames()
    {
        $keys = array_filter(array_keys($this->data), function($key) {
            return strpos($key, '_') !== 0;
        });
        return $keys;
    }

    /**
     * Get an array of this resource's properties and their values.
     *
     * @return array
     */
    public function getProperties()
    {
        $keys = $this->getPropertyNames();
        return array_intersect_key($this->data, array_flip($keys));
    }
}
