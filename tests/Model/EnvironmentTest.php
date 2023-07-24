<?php

namespace Platformsh\Client\Tests\Model;

use PHPUnit\Framework\TestCase;
use Platformsh\Client\Model\Environment;

class EnvironmentTest extends TestCase
{
    public function testGetSshUrl()
    {
        $multiApp = [
            'ssh' => ['href' => 'ssh://incorrect-fallback@ssh.example.com'],
            'pf:ssh:app1' => ['href' => 'ssh://projectid-envmachinename--app1@ssh.example.com'],
            'pf:ssh:app2' => ['href' => 'ssh://projectid-envmachinename--app2@ssh.example.com'],
        ];
        $haMultiAppWithInstanceDefault = [
            'ssh' => ['href' => 'ssh://incorrect-fallback@ssh.example.com'],
            'pf:ssh:app1' => ['href' => 'ssh://projectid-envmachinename--app1--2@ssh.example.com'],
            'pf:ssh:app1:0' => ['href' => 'ssh://projectid-envmachinename--app1--0@ssh.example.com'],
            'pf:ssh:app1:1' => ['href' => 'ssh://projectid-envmachinename--app1--1@ssh.example.com'],
            'pf:ssh:app1:2' => ['href' => 'ssh://projectid-envmachinename--app1--2@ssh.example.com'],
            'pf:ssh:app2' => ['href' => 'ssh://projectid-envmachinename--app1--2@ssh.example.com'],
            'pf:ssh:app2:0' => ['href' => 'ssh://projectid-envmachinename--app2--0@ssh.example.com'],
            'pf:ssh:app2:1' => ['href' => 'ssh://projectid-envmachinename--app2--1@ssh.example.com'],
            'pf:ssh:app2:2' => ['href' => 'ssh://projectid-envmachinename--app2--2@ssh.example.com'],
        ];
        $haMultiAppNoInstanceDefault = [
            'ssh' => ['href' => 'ssh://incorrect-fallback@ssh.example.com'],
            'pf:ssh:app1:0' => ['href' => 'ssh://projectid-envmachinename--app1--0@ssh.example.com'],
            'pf:ssh:app1:1' => ['href' => 'ssh://projectid-envmachinename--app1--1@ssh.example.com'],
            'pf:ssh:app1:2' => ['href' => 'ssh://projectid-envmachinename--app1--2@ssh.example.com'],
            'pf:ssh:app2:0' => ['href' => 'ssh://projectid-envmachinename--app2--0@ssh.example.com'],
            'pf:ssh:app2:1' => ['href' => 'ssh://projectid-envmachinename--app2--1@ssh.example.com'],
            'pf:ssh:app2:2' => ['href' => 'ssh://projectid-envmachinename--app2--2@ssh.example.com'],
        ];

        /** @var array{'_links': string[], 'app': string, 'instance': string, 'result': string|false}[] $cases */
        $cases = [
            [
                '_links' => $multiApp,
                'app' => 'app1',
                'instance' => '',
                'result' => 'projectid-envmachinename--app1@ssh.example.com',
            ],
            [
                '_links' => $multiApp,
                'app' => 'app1',
                'instance' => '1',
                'result' => false,
            ],
            [
                '_links' => $haMultiAppWithInstanceDefault,
                'app' => 'app1',
                'instance' => '',
                'result' => 'projectid-envmachinename--app1--2@ssh.example.com',
            ],
            [
                '_links' => $haMultiAppWithInstanceDefault,
                'app' => 'app1',
                'instance' => '0',
                'result' => 'projectid-envmachinename--app1--0@ssh.example.com',
            ],
            [
                '_links' => $haMultiAppNoInstanceDefault,
                'app' => 'app1',
                'instance' => '',
                'result' => 'projectid-envmachinename--app1--0@ssh.example.com',
            ],
            [
                '_links' => $haMultiAppNoInstanceDefault,
                'app' => 'app1',
                'instance' => '1',
                'result' => 'projectid-envmachinename--app1--1@ssh.example.com',
            ],
            [
                '_links' => $haMultiAppNoInstanceDefault,
                'app' => 'app1',
                'instance' => '3',
                'result' => false,
            ],
            [
                '_links' => $haMultiAppNoInstanceDefault,
                'app' => 'app2',
                'instance' => '',
                'result' => 'projectid-envmachinename--app2--0@ssh.example.com',
            ],
        ];
        foreach ($cases as $i => $case) {
            $environment = new Environment(['id' => 'main', 'status' => 'active', '_links' => $case['_links']], 'https://example.com/projects/foo');
            if ($case['result'] === false) {
                try {
                    $environment->getSshUrl($case['app'], $case['instance']);
                } catch (\InvalidArgumentException $e) {
                    $this->assertContains('SSH URL not found for instance', $e->getMessage(), "case $i");
                }
                continue;
            }
            $result = $environment->getSshUrl($case['app'], $case['instance']);
            $this->assertEquals($case['result'], $result, "case $i");
        }
    }
}
