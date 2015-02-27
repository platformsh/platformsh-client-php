<?php

namespace Platformsh\Client\Tests;

use Platformsh\Client\PlatformClient;

class PlatformClientTest extends \PHPUnit_Framework_TestCase
{

    /** @var MockConnector */
    protected $connector;

    /** @var PlatformClient */
    protected $client;

    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        $this->connector = new MockConnector();
        $this->client = new PlatformClient($this->connector);
    }

    public function testGetProjectsNone()
    {
        $this->connector->setExpectedResult(['projects' => []]);
        $this->assertEquals([], $this->client->getProjects());

        $this->assertFalse($this->client->getProject('test'));
    }

    public function testGetProjectsSingle()
    {
        $testProject = [
          'name' => 'Test project',
          'endpoint' => 'https://example.com/api/projects/test',
        ];
        $this->connector->setExpectedResult(['projects' => [$testProject]]);
        $projects = $this->client->getProjects();
        $this->assertEquals($testProject['name'], $projects['test']['name']);
        $this->assertEquals($testProject['endpoint'], $projects['test']['endpoint']);

        $project = $this->client->getProject('test');
        $this->assertEquals($testProject['name'], $project['name']);
        $this->assertEquals($testProject['endpoint'], $project['endpoint']);
    }
}
