<?php

declare(strict_types=1);

/** @noinspection PhpDocMissingThrowsInspection */
/** @noinspection PhpUnhandledExceptionInspection */

namespace Platformsh\Client\Model;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use Platformsh\Client\Exception\ApiResponseException;
use Platformsh\Client\Exception\OperationUnavailableException;
use Psr\Http\Message\RequestInterface;

/**
 * The base class for API resources.
 */
abstract class ApiResourceBase implements \ArrayAccess
{
    protected static ?string $collectionItemsKey;

    protected static array $required = [];

    protected ClientInterface $client;

    protected string $baseUrl;

    protected array $data;

    protected bool $isFull = false;

    protected ?Collection $parentCollection = null;

    /**
     * @param array           $data    The raw data for the resource
     *                                 (as deserialized from JSON).
     * @param string|null $baseUrl The absolute URL to the resource or its
     *                                 collection.
     * @param ?ClientInterface $client  A suitably configured Guzzle client.
     * @param bool $full    Whether the data is a complete
     *                                 representation of the resource.
     */
    public function __construct(array $data, ?string $baseUrl = null, ?ClientInterface $client = null, bool $full = true)
    {
        $this->client = $client ?: new Client();
        $this->baseUrl = (string) $baseUrl;
        $this->isFull = $full;
        $this->setData($data);
    }

    /**
     * Magic getter, allowing resource properties to be accessed.
     *
     * Properties can be documented in implementing classes' docblocks.
     *
     * @return mixed
     */
    public function __get(string $name)
    {
        return $this->getProperty($name, false);
    }

    /**
     * @return bool
     */
    public function __isset(string $name)
    {
        return $this->hasProperty($name);
    }

    /**
     * Prevent setting magic properties.
     *
     * @throws \BadMethodCallException
     */
    public function __set(string $name, mixed $value)
    {
        throw new \BadMethodCallException('Properties are read-only');
    }

    public function setBaseUrl(string $baseUrl): void
    {
        $this->baseUrl = $baseUrl;
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->hasProperty($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->getProperty($offset, false);
    }

    /**
     * @throws \BadMethodCallException
     */
    public function offsetSet(mixed $offset, $value): void
    {
        throw new \BadMethodCallException('Properties are read-only');
    }

    /**
     * @throws \BadMethodCallException
     */
    public function offsetUnset(mixed $offset): void
    {
        throw new \BadMethodCallException('Properties are read-only');
    }

    /**
     * Get all the API data for this resource.
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Ensure that this is a full representation of the resource (not a stub).
     */
    public function ensureFull(): void
    {
        if (! $this->isFull) {
            $this->refresh();
        }
    }

    /**
     * Get a resource by its ID.
     *
     * @param string $id            The ID of the resource, or the
     *                                       full URL.
     * @param ?string $collectionUrl The URL of the collection.
     * @param ClientInterface $client        A suitably configured Guzzle
     *                                       client.
     *
     * @return static|false The resource object, or false if the resource is
     *                      not found.
     *@throws \InvalidArgumentException if the resource ID is invalid
     */
    public static function get(string $id, ?string $collectionUrl, ClientInterface $client): false|static
    {
        if ($id === '.' || $id === '..') {
            throw new \InvalidArgumentException('Invalid resource ID: ' . $id);
        }
        try {
            $url = $collectionUrl ? rtrim($collectionUrl, '/') . '/' . urlencode($id) : $id;
            $request = new Request('get', $url);
            $data = self::send($request, $client);

            return new static($data, $url, $client, true);
        } catch (BadResponseException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Create a resource.
     *
     * @return Result
     * @noinspection PhpMissingReturnTypeInspection
     */
    public static function create(array $body, string $collectionUrl, ClientInterface $client)
    {
        if ($errors = static::checkNew($body)) {
            $message = 'Cannot create resource due to validation error(s): ' . implode('; ', $errors);
            throw new \InvalidArgumentException($message);
        }

        $request = new Request('post', $collectionUrl, [
            'Content-Type' => 'application/json',
        ], \GuzzleHttp\Utils::jsonEncode($body));
        $data = self::send($request, $client);

        return new Result($data, $collectionUrl, $client, static::class);
    }

    /**
     * Send a Guzzle request.
     *
     * Using this method allows exceptions to be standardized.
     *
     * @internal
     */
    public static function send(RequestInterface $request, ClientInterface $client, array $options = []): array
    {
        try {
            $response = $client->send($request, $options);
            $body = $response->getBody()->getContents();
            $data = [];
            if ($body) {
                $response->getBody()->seek(0);
                $body = $response->getBody()->getContents();
                $data = \GuzzleHttp\Utils::jsonDecode($body, true);
            }

            return (array) $data;
        } catch (GuzzleException $e) {
            throw ApiResponseException::wrapGuzzleException($e);
        }
    }

    /**
     * Get the required properties for creating a new resource.
     */
    public static function getRequired(): array
    {
        return static::$required;
    }

    /**
     * Returns a list of resources (a collection).
     *
     * @param string $url     The collection URL.
     * @param int $limit   A limit on the number of resources to
     *                                 return. Deprecated.
     * @param array           $options An array of additional Guzzle request
     *                                 options.
     * @param ClientInterface $client  A suitably configured Guzzle client.
     *
     * @return static[]
     */
    public static function getCollection(string $url, int $limit, array $options, ClientInterface $client): array
    {
        $items = static::getCollectionWithParent($url, $client, $options)['items'];

        if (! empty($limit) && count($items) > $limit) {
            $items = array_slice($items, 0, $limit);
        }

        return $items;
    }

    /**
     * Returns a list of resources and the Collection that contained them.
     *
     * @param string $url     The collection URL.
     * @param ClientInterface $client A suitably configured Guzzle client.
     * @param array           $options An array of additional Guzzle request
     *                                 options.
     *
     * @return array{items: static[], collection: Collection}
     */
    public static function getCollectionWithParent(string $url, ClientInterface $client, array $options = []): array
    {
        $request = new Request('GET', $url);
        $data = self::send($request, $client, $options);
        $collection = new Collection($data, $client, $url);
        return [
            'items' => static::wrapCollection($collection, $url, $client),
            'collection' => $collection,
        ];
    }

    /**
     * Create an array of resource instances from a collection's JSON data.
     *
     * @param array|Collection $data    The deserialized JSON from the
     *                                  collection (i.e. a list of resources,
     *                                  each of which is an array of data).
     * @param string $baseUrl The URL to the collection.
     * @param ClientInterface  $client  A suitably configured Guzzle client.
     *
     * @return static[]
     */
    public static function wrapCollection(array|Collection $data, string $baseUrl, ClientInterface $client): array
    {
        if ($data instanceof Collection) {
            $parent = $data;
            $data = $data->getData();
        } else {
            $parent = new Collection($data, $client, $baseUrl);
        }
        $resources = [];
        $items = $data;
        if (isset(static::$collectionItemsKey)) {
            $items = $items[static::$collectionItemsKey];
        }
        foreach ($items as $item) {
            $resource = new static($item, $baseUrl, $client);
            $resource->setParentCollection($parent);
            $resources[] = $resource;
        }

        return $resources;
    }

    /**
     * Execute an operation on the resource.
     */
    public function runOperation(string $op, string $method = 'POST', array $body = []): Result
    {
        if (! $this->operationAvailable($op, true)) {
            throw new OperationUnavailableException("Operation not available: {$op}");
        }
        $request = new Request($method, $this->getLink("#{$op}"), [
            'Content-Type' => 'application/json',
        ], $body ? \json_encode($body) : null);
        $data = $this->send($request, $this->client);

        return new Result($data, $this->baseUrl, $this->client, static::class);
    }

    /**
     * Check whether a property exists in the resource.
     */
    public function hasProperty(string $property, bool $lazyLoad = true): bool
    {
        if (! $this->isProperty($property)) {
            return false;
        }
        if (! array_key_exists($property, $this->data) && $lazyLoad) {
            $this->ensureFull();
        }

        return array_key_exists($property, $this->data);
    }

    /**
     * Get a property of the resource.
     *
     * @return mixed|null
     *   The property value, or null if the property does not exist (and
     *   $required is false).
     *@throws \InvalidArgumentException If $required is true and the property
     *                                   is not found.
     */
    public function getProperty(string $property, bool $required = true, bool $lazyLoad = true): mixed
    {
        if (! $this->hasProperty($property, $lazyLoad)) {
            if ($required) {
                throw new \InvalidArgumentException("Property not found: {$property}");
            }
            return null;
        }

        return $this->data[$property];
    }

    /**
     * Delete the resource.
     */
    public function delete(): Result
    {
        $data = $this->sendRequest($this->getUri(), 'delete');

        return new Result($data, $this->getUri(), $this->client, static::class);
    }

    /**
     * Update the resource.
     *
     * This updates the resource's internal data with the API response.
     */
    public function update(array $values): Result
    {
        if ($errors = $this->checkUpdate($values)) {
            $message = 'Cannot update resource due to validation error(s): ' . implode('; ', $errors);
            throw new \InvalidArgumentException($message);
        }
        $data = $this->runOperation('edit', 'patch', $values)->getData();
        if (isset($data['_embedded']['entity'])) {
            $this->setData($data['_embedded']['entity']);
            $this->isFull = true;
        }

        return new Result($data, $this->baseUrl, $this->client, static::class);
    }

    /**
     * Get the resource's URI.
     */
    public function getUri(bool $absolute = true): string
    {
        return $this->getLink('self', $absolute);
    }

    /**
     * Refresh the resource.
     */
    public function refresh(array $options = []): void
    {
        $request = new Request('get', $this->getUri());
        $this->setData(self::send($request, $this->client, $options));
        $this->isFull = true;
    }

    /**
     * Check whether an operation is available on the resource.
     */
    public function operationAvailable(string $op, bool $refreshDuringCheck = false): bool
    {
        // Ensure this resource is a full representation.
        if (! $this->isFull) {
            $this->refresh();
            $refreshDuringCheck = false;
        }

        // Check if the operation is available in the HAL links.
        $available = $this->isOperationAvailable($op);
        if ($available) {
            return true;
        }

        // If not, and $refreshDuringCheck is on, then refresh the resource.
        if ($refreshDuringCheck) {
            $this->refresh();
            $available = $this->isOperationAvailable($op);
        }

        return $available;
    }

    /**
     * Check whether the resource has a link.
     */
    public function hasLink($rel): bool
    {
        return isset($this->data['_links'][$rel]['href']);
    }

    /**
     * Get a link for a given resource relation.
     */
    public function getLink(string $rel, bool $absolute = true): string
    {
        if (! $this->hasLink($rel)) {
            throw new \InvalidArgumentException("Link not found: {$rel}");
        }
        $url = $this->data['_links'][$rel]['href'];
        if ($absolute || str_contains($url, '//')) {
            $url = $this->makeAbsoluteUrl($url);
        }
        return $url;
    }

    /**
     * Get a list of this resource's property names.
     *
     * @return string[]
     */
    public function getPropertyNames(): array
    {
        return array_filter(array_keys($this->data), [$this, 'isProperty']);
    }

    /**
     * Get an array of this resource's properties and their values.
     */
    public function getProperties(bool $lazyLoad = true): array
    {
        if ($lazyLoad) {
            $this->ensureFull();
        }
        $keys = $this->getPropertyNames();

        return array_intersect_key($this->data, array_flip($keys));
    }

    /**
     * Returns the wrapping collection, if this resource's data was fetched via one.
     *
     * Useful for pagination.
     */
    public function getParentCollection(): ?Collection
    {
        return $this->parentCollection;
    }

    /**
     * A simple helper function to send an HTTP request.
     */
    protected function sendRequest(string $url, string $method = 'get', array $options = []): array
    {
        return $this->send(
            new Request($method, $url),
            $this->client,
            $options
        );
    }

    /**
     * Validate a new resource.
     *
     * @return string[] An array of validation errors.
     */
    protected static function checkNew(array $data): array
    {
        $errors = [];
        if ($missing = array_diff(static::getRequired(), array_keys($data))) {
            $errors[] = 'Missing: ' . implode(', ', $missing);
        }
        foreach ($data as $key => $value) {
            $errors += static::checkProperty($key, $value);
        }
        return $errors;
    }

    /**
     * Validate a property of the resource, for creating or updating.
     *
     * @return string[] An array of validation errors.
     */
    protected static function checkProperty(string $property, mixed $value): array
    {
        return [];
    }

    /**
     * Run a long-running operation.
     *
     *@see Resource::runOperation()
     *
     * @deprecated use runOperation() instead
     */
    protected function runLongOperation(string $op, string $method = 'post', array $body = []): Activity
    {
        @trigger_error('This method is deprecated as actions may return multiple activities. Use runOperation() if possible.', E_USER_DEPRECATED);
        $result = $this->runOperation($op, $method, $body);
        $activities = $result->getActivities();
        if (count($activities) !== 1) {
            trigger_error(sprintf('Expected one activity, found %d', count($activities)), E_USER_WARNING);
        }

        return reset($activities);
    }

    /**
     * Validate values for update.
     *
     * @return string[] An array of validation errors.
     */
    protected static function checkUpdate(array $values): array
    {
        $errors = [];
        foreach ($values as $key => $value) {
            $errors += static::checkProperty($key, $value);
        }
        return $errors;
    }

    protected function setData(array $data): void
    {
        $this->data = $data;
    }

    /**
     * Internal: check whether an operation is available on the resource.
     */
    protected function isOperationAvailable(string $op): bool
    {
        return isset($this->data['_links']["#{$op}"]['href']);
    }

    /**
     * Make a URL absolute, based on the base URL.
     */
    protected function makeAbsoluteUrl(string $relativeUrl, ?string $baseUrl = null): string
    {
        $baseUrl = $baseUrl ?: $this->baseUrl;
        if (empty($baseUrl)) {
            throw new \RuntimeException('No base URL');
        }
        $base = Utils::uriFor($baseUrl);
        $target = Utils::uriFor($relativeUrl);
        // Ensure an absolute base URL overrides an absolute target URL.
        if ($base->getScheme() !== '' && \in_array($target->getScheme(), ['http', 'https'], true)) {
            return (string) $base->withPath($target->getPath());
        }
        return (string) $base->withPath((string) $target);
    }

    protected function isProperty(string $key): bool
    {
        return $key !== '_links' && $key !== '_embedded';
    }

    /**
     * Sets a parent collection for this resource.
     */
    protected function setParentCollection(Collection $parent): void
    {
        $this->parentCollection = $parent;
    }
}
