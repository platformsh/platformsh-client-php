<?php

namespace Platformsh\Client\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class MockClient extends Client
{

    /**
     * @inheritdoc
     */
    public function __construct(array $config = [])
    {
        $handler = new MockHandler([
          new Response(
            isset($config['mockStatus']) ? $config['mockStatus'] : 200,
            [
              'Content-Type' => 'application/json',
            ],
            isset($config['mockValues']) ? json_encode($config['mockValues']) : ''
          )
        ]);
        unset($config['mockStatus'], $config['mockValues']);

        $config['handler'] = HandlerStack::create($handler);
        parent::__construct($config);
    }

}
