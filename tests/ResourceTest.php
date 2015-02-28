<?php

namespace Platformsh\Client\Tests;

use Platformsh\Client\Model\Resource;

class ResourceTest extends \PHPUnit_Framework_TestCase
{

    /** @var array */
    protected $properties;

    /** @var \Platformsh\Client\Model\Resource */
    protected $resource;

    public function setUp()
    {
        $this->properties = array(
          'id' => 'test-id',
          'name' => 'test name',
          'array' => array(),
          'integer' => 123,
        );
        $data = $this->properties + array(
            '_embedded' => array(),
            '_links' => array(
              'self' => array(
                'href' => 'https://example.com/',
              ),
              '#operate' => array(
                'href' => 'https://example.com/operate',
              ),
            ),
          );
        $this->resource = new Resource($data);
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
}
