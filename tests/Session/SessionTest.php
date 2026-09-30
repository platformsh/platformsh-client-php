<?php

declare(strict_types=1);

namespace Platformsh\Client\Tests\Session;

use PHPUnit\Framework\TestCase;
use Platformsh\Client\Session\Session;
use Platformsh\Client\Session\Storage\SessionStorageInterface;

class SessionTest extends TestCase
{
    private SessionStorageInterface $storage;

    protected function setUp(): void
    {
        $this->storage = new class() implements SessionStorageInterface {
            public array $sessions = [];

            public int $saveCount = 0;

            public function load(string $sessionId): array
            {
                return $this->sessions[$sessionId] ?? [];
            }

            public function save(string $sessionId, array $data): void
            {
                $this->saveCount++;
                $this->sessions[$sessionId] = $data;
            }
        };
    }

    public function testGetId(): void
    {
        $this->assertSame('default', (new Session())->getId());
        $this->assertSame('foo', (new Session('foo'))->getId());
    }

    public function testReload(): void
    {
        $session = new Session('test', [], $this->storage);
        $this->storage->sessions['test'] = [
            'token' => 'old',
        ];
        $this->assertSame('old', $session->get('token'));

        // Another process changes the stored data.
        $this->storage->sessions['test'] = [
            'token' => 'new',
        ];
        $this->assertSame('old', $session->get('token'));
        $session->reload();
        $this->assertSame('new', $session->get('token'));

        // Reloaded data is the new baseline for save().
        $session->save();
        $this->assertSame(0, $this->storage->saveCount);
    }

    public function testReloadDiscardsUnsavedChanges(): void
    {
        $session = new Session('test', [], $this->storage);
        $session->set('token', 'unsaved');
        $session->reload();
        $this->assertNull($session->get('token'));
    }

    public function testReloadWithoutStorage(): void
    {
        $session = new Session('test', [
            'token' => 'foo',
        ]);
        $session->reload();
        $this->assertSame('foo', $session->get('token'));
    }
}
