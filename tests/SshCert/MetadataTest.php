<?php

namespace Platformsh\Client\Tests\SshCert;

use PHPUnit\Framework\TestCase;
use Platformsh\Client\SshCert\Metadata;

class MetadataTest extends TestCase {
    private $metadata;

    public function setUp()
    {
        // Key generated with:
        // ssh-keygen -s test -I 'foo' -V '20140513120000:20200429060000' test.pub
        $this->metadata = new Metadata(\file_get_contents(dirname(__DIR__) . '/data/ssh-certs/test-cert.pub'));
    }

    public function testGetValidAfter() {
        $this->assertEquals(strtotime('2014-05-13T12:00:00Z'), $this->metadata->getValidAfter());
    }

    public function testGetValidBefore() {
        $this->assertEquals(strtotime('2020-04-29T06:00:00Z'), $this->metadata->getValidBefore());
    }

    public function testGetKeyId() {
        $this->assertEquals('foo', $this->metadata->getKeyId());
    }
}
