<?php

namespace Platformsh\Client\Model;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;

/**
 * Represents Platform.sh setup options api requests.
 *
 */

class SetupOptions
{

    /** @var ClientInterface */
    protected $client;

    /**
     * Post request for setup options data.
     *
     * @param string          $body          array of the data to send in the post.
     * @param string          $url           The URL of the setup options api.
     * @param ClientInterface $client        A suitably configured Guzzle
     *                                       client.
     *
     * @return array The returned setup options api data in array format or an
     *                  error if one is returned.
     */
    public static function getList(array $body, $url = null, ClientInterface $client)
    {
        try {
            $request = $client->createRequest('post', $url, ['json' => $body]);
            $response = $client->send($request);
            $body = $response->getBody()->getContents();
            $data = [];
            if ($body) {
                $response->getBody()->seek(0);
                $data = $response->json();
            }
            return $data;
        } catch (BadResponseException $e) {
            throw $e;
        }
    }
}
