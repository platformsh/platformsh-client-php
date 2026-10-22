<?php

declare(strict_types=1);

namespace Platformsh\Client\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Platformsh\Client\Model\ApiResourceBase;

class ResourceTest extends TestCase
{
    protected array $properties;

    protected ApiResourceBase $resource;

    protected function setUp(): void
    {
        $this->properties = [
            'id' => 'test-id',
            'name' => 'test name',
            'array' => [],
            'integer' => 123,
        ];
        $data = $this->properties + [
            '_embedded' => [],
            '_links' => [
                'self' => [
                    'href' => 'https://example.com/resources/test-id',
                ],
                '#operate' => [
                    'href' => '/resources/test-id/operate',
                ],
            ],
        ];
        $this->resource = new MockApiResource($data, 'https://example.com/', null, true);
    }

    /**
     * Test Resource::getProperties().
     */
    public function testGetProperties()
    {
        $this->assertEquals(array_keys($this->properties), array_values($this->resource->getPropertyNames()));
        $this->assertEquals($this->properties, $this->resource->getProperties());
    }

    /**
     * Test Resource::getProperty().
     */
    public function testGetProperty()
    {
        $this->assertEquals('test-id', $this->resource['id']);
        $this->assertEquals('test name', $this->resource->getProperty('name'));
        $this->expectException('\InvalidArgumentException');
        $this->resource->getProperty('nonexistent');
    }

    /**
     * Test Resource::operationAvailable().
     */
    public function testOperationAvailable()
    {
        $this->assertTrue($this->resource->operationAvailable('operate'));
        $this->assertFalse($this->resource->operationAvailable('nonexistent'));
    }

    /**
     * Test Resource::operationAllowed().
     */
    public function testGetLink()
    {
        $this->assertNotEmpty($this->resource->getLink('self'));
        $this->assertNotEmpty($this->resource->getLink('#operate'));
        $this->expectException('\InvalidArgumentException');
        $this->resource->getLink('nonexistent');
    }

    /**
     * Test new resource validation.
     */
    public function testRequiredPropertiesBlockCreation()
    {
        $this->expectException('\InvalidArgumentException');
        MockApiResource::create([], '', MockClient::create());
    }

    /**
     * Test updating resource validation.
     */
    public function testInvalidPropertiesBlockUpdate()
    {
        $resource = new MockApiResource([]);
        $this->expectException('\InvalidArgumentException');
        $resource->update([
            'testProperty' => 2,
        ]);
    }

    public static function operationBodyProvider(): array
    {
        return [
            'POST without a body' => ['POST', [], '{}'],
            'POST with a body' => ['post', [
                'foo' => 'bar',
            ], '{"foo":"bar"}'],
            'lowercase POST' => ['post', [], '{}'],
            'PATCH without a body' => ['PATCH', [], '{}'],
            'PUT without a body' => ['PUT', [], ''],
            'GET' => ['GET', [], ''],
            'lowercase GET' => ['get', [], ''],
            'GET with a body' => ['GET', [
                'foo' => 'bar',
            ], '{"foo":"bar"}'],
            'DELETE' => ['DELETE', [], ''],
        ];
    }

    /**
     * Test the request body sent by Resource::runOperation().
     */
    #[DataProvider('operationBodyProvider')]
    public function testRunOperationBody(string $method, array $body, string $expected): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200)]));
        $stack->push(Middleware::history($history));
        $resource = new MockApiResource([
            '_links' => [
                'self' => [
                    'href' => 'https://example.com/resources/test-id',
                ],
                '#operate' => [
                    'href' => '/resources/test-id/operate',
                ],
            ],
        ], 'https://example.com/', new Client([
            'handler' => $stack,
        ]));

        $resource->runOperation('operate', $method, $body);

        $this->assertCount(1, $history);
        $this->assertSame(strtoupper($method), $history[0]['request']->getMethod());
        $this->assertSame($expected, (string) $history[0]['request']->getBody());
    }
}
