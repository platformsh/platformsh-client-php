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
        $this->connector->setMockResult(['projects' => []]);

        $this->assertEquals([], $this->client->getProjects());
        $this->assertFalse($this->client->getProject('test'));
    }

    public function testGetProjectsSingle()
    {
        $testProject = [
          'name' => 'Test project',
          'endpoint' => 'https://example.com/api/projects/test',
        ];
        $this->connector->setMockResult(['projects' => [$testProject]]);
        $projects = $this->client->getProjects();
        $this->assertEquals($testProject['name'], $projects['test']['name']);
        $this->assertEquals($testProject['endpoint'], $projects['test']['endpoint']);

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

    public function testGetSshKeysNone()
    {
        $this->connector->setMockResult(['ssh_keys' => []]);
        $sshKeys = $this->client->getSshKeys();
        $this->assertEquals([], $sshKeys);
        $this->assertFalse($this->client->getSshKey(0));
    }

    public function testGetSshKeySingle()
    {
        $testKey = [
          'title' => 'Test key',
          'fingerprint' => 'xyz',
        ];
        $testKeyId = rand(1, 500);
        $this->connector->setMockResult(['ssh_keys' => [$testKeyId => $testKey]]);
        $sshKeys = $this->client->getSshKeys();
        $this->assertEquals($testKey['title'], $sshKeys[$testKeyId]['title']);
        $this->assertEquals($sshKeys[$testKeyId], $this->client->getSshKey($testKeyId));
        $this->assertFalse($this->client->getSshKey($testKeyId + 1));
    }

    public function testAddSshKeyInvalid()
    {
        $this->setExpectedException('InvalidArgumentException');
        $this->client->addSshKey('test invalid key');
    }

    public function testAddSshKeyErrorResponse()
    {
        $this->connector->setMockResult([], 401);
        $this->setExpectedException('\\GuzzleHttp\\Exception\\ClientException');
        $this->client->addSshKey('ssh-rsa testKeyValue');
    }

    public function testAddSshKeyValid()
    {
        $this->connector->setMockResult([], 200);
        $result = $this->client->addSshKey('ssh-rsa testKeyValue');
        $this->assertNotEmpty($result);
    }
}
