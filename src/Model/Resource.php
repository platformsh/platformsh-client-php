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
        $uri = $this->determineUri($data);

        $this->client = $client ?: new Client();

        $this->hal = $hal ?: new Hal();
        $this->hal->setUri($uri);
        $this->hal->setData($data);

        $this->data = $data;
    }

    /**
     * Find out the resource URI from its data.
     *
     * @param array $data
     *
     * @throws \Exception
     *
     * @return string
     */
    protected function determineUri(array $data)
    {
        if (isset($data['_links']['self']['href'])) {
            return $data['_links']['self']['href'];
        }
        throw new \Exception("Cannot determine URI");
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
