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

    public function testGetProjectStubs()
    {
        $testProject = [
          'id' => 'test',
          'title' => 'Test project',
          'endpoint' => 'https://region-1.example.com/api/projects/test',
        ];
        $this->connector->setMockResult(['projects' => [$testProject]]);
        $projects = $this->client->getProjectStubs();
        $this->assertEquals($testProject['title'], $projects[0]->title);
        $this->assertEquals($testProject['endpoint'], $projects[0]->endpoint);
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
