<?php

namespace Platformsh\Client\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Ring\Client\MockHandler;

class MockClient extends Client
{

    /**
     * @inheritdoc
     */
    public function __construct(array $config = [])
    {
        if (!isset($config['handlers'])) {
            $handler = new MockHandler(
              [
                'status' => isset($config['mockStatus']) ? $config['mockStatus'] : 200,
                'body' => isset($config['mockValues']) ? json_encode($config['mockValues']) : '',
              ]
            );
            $config['handlers'] = [$handler];
            unset($config['mockStatus'], $config['mockValues']);
        }
        parent::__construct($config);
    }

}
