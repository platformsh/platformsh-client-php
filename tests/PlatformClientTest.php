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
        $this->assertEquals($testProject['endpoint'], $projects[0]['endpoint']);
        $this->assertEquals($this->apiUrl . '/projects/test/invitations', $projects[0]->getLink('invitations'));

        $project = $this->client->getProject('test');
        $this->assertEquals($testProject['name'], $project['name']);
        $this->assertEquals($testProject['endpoint'], $project['endpoint']);
        $this->assertEquals($this->apiUrl . '/projects/test/invitations', $project->getLink('invitations'));
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
