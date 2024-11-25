<?php

declare(strict_types=1);

namespace Platformsh\Client\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class MockClient
{
    public static function create(array $config = []): ClientInterface
    {
        $handler = new MockHandler([
            new Response(
                $config['mockStatus'] ?? 200,
                [
                    'Content-Type' => 'application/json',
                ],
                isset($config['mockValues']) ? json_encode($config['mockValues']) : ''
            ),
        ]);
        unset($config['mockStatus'], $config['mockValues']);

        $config['handler'] = HandlerStack::create($handler);
        return new Client($config);
    }
}
