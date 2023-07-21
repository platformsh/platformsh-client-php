<?php

namespace Platformsh\Client\Tests;

class ResourceTest extends \PHPUnit_Framework_TestCase
{

    /** @var array */
    protected $properties;

    /** @var \Platformsh\Client\Model\Resource */
    protected $resource;

    public function setUp()
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
        $this->resource = new MockResource($data, 'https://example.com/', null, true);
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
        $this->setExpectedException('\InvalidArgumentException');
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
        $this->setExpectedException('\InvalidArgumentException');
        $this->resource->getLink('nonexistent');
    }

    /**
     * Test new resource validation.
     */
    public function testRequiredPropertiesBlockCreation()
    {
        $mockClient = new MockClient();
        $this->setExpectedException('\InvalidArgumentException');
        MockResource::create([], '', $mockClient);
    }

    /**
     * Test updating resource validation.
     */
    public function testInvalidPropertiesBlockUpdate()
    {
        $resource = new MockResource([]);
        $this->setExpectedException('\InvalidArgumentException');
        $resource->update(['testProperty' => 2]);
    }
}
