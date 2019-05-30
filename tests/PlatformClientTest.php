<?php

namespace Platformsh\Client\Tests;

use PHPUnit\Framework\TestCase;
use Platformsh\Client\PlatformClient;

class PlatformClientTest extends TestCase
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
        $this->connector->setMockResult(['projects' => []]);

        $this->assertEquals([], $this->client->getProjects());
        $this->assertFalse($this->client->getProject('test'));
    }

    public function testGetProjectsSingle()
    {
        $testProject = [
          'id' => 'test',
          'name' => 'Test project',
          'endpoint' => 'https://example.com/api/projects/test',
        ];
        $this->connector->setMockResult(['projects' => [$testProject]]);
        $projects = $this->client->getProjects();
        $this->assertEquals($testProject['name'], $projects[0]['name']);
        $this->assertEquals($testProject['endpoint'], $projects[0]['endpoint']);

        $project = $this->client->getProject('test');
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
        $this->expectException('InvalidArgumentException');
        $this->client->addSshKey('test invalid key');
    }
}
