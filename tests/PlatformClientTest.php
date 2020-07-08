<?php

namespace Platformsh\Client\Tests;

use Platformsh\Client\PlatformClient;

class PlatformClientTest extends \PHPUnit_Framework_TestCase
{

    /** @var MockConnector */
    protected $connector;

    /** @var PlatformClient */
    protected $client;

    protected $apiUrl;

    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        $this->apiUrl = 'https://api.example.com';
        $this->connector = new MockConnector(['api_url' => $this->apiUrl]);
        $this->client = new PlatformClient($this->connector);
    }

    public function testGetProjectsNone()
    {
        $this->connector->setMockResult(['projects' => []]);
        $this->assertEquals([], $this->client->getProjects());

        $this->connector->setMockResult([], 404);
        $this->assertFalse($this->client->getProject('test'));
    }

    public function testGetProjectsSingle()
    {
        $testProject = [
          'id' => 'test',
          'name' => 'Test project',
          'endpoint' => 'https://region-1.example.com/api/projects/test',
        ];
        $this->connector->setMockResult(['projects' => [$testProject]]);
        $projects = $this->client->getProjects();
        $this->assertEquals($testProject['name'], $projects[0]['name']);
        $this->assertEquals($this->apiUrl . '/projects/test', $projects[0]['endpoint']);

        $project = $this->client->getProject('test');
        $this->assertEquals($testProject['name'], $project['name']);
        $this->assertEquals($this->apiUrl . '/projects/test', $project['endpoint']);

        // Test endpoint without an API gateway URL configured.
        $connector = new MockConnector(['api_url' => '']);
        $connector->setMockResult(['projects' => [$testProject]]);
        $project = (new PlatformClient($connector))->getProject('test');
        $this->assertEquals($testProject['name'], $project['name']);
        $this->assertEquals($testProject['endpoint'], $project['endpoint']);
    }

    public function testGetProjectDirect()
    {
        $this->connector->setMockResult(['id' => 'test']);
        $project = $this->client->getProjectDirect('test', 'example.com');
        $this->assertInstanceOf('\\Platformsh\\Client\\Model\\Project', $project);
        $this->connector->setMockResult([], 404);
        $project = $this->client->getProjectDirect('test2', 'example.com');
        $this->assertFalse($project);
    }

    public function testAddSshKeyInvalid()
    {
        $this->setExpectedException('InvalidArgumentException');
        $this->client->addSshKey('test invalid key');
    }
}
