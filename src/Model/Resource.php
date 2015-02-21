<?php

namespace Platformsh\Client\Model;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Nocarrier\Hal;

class Resource implements ResourceInterface
{

    /**
     * @var ClientInterface
     */
    protected $client;

    /** @var array */
    protected $data;

    /** @var Hal */
    protected $hal;

    /**
     * @param array           $data
     * @param ClientInterface $client
     * @param object          $hal
     */
    public function __construct(array $data = [], ClientInterface $client = null, $hal = null)
    {
        $this->client = $client ?: new Client();

        $this->data = $data;

        $this->hal = $hal ?: new Hal();
        $this->hal->setUri($this->getUri());
        $this->hal->setData($data);
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
        $data = $response->json();
        $this->hal->setData($data);
    }

    public function getLink($rel)
    {
        $link = $this->hal->getLink($rel);
        if (!$link) {
            throw new \InvalidArgumentException("Link not found: $rel");
        }

        /** @var $link \NoCarrier\HalLink */

        return $link->getUri();
    }
}
