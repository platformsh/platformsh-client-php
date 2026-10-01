<?php

declare(strict_types=1);

namespace Platformsh\Client\Tests\Session\Storage;

use PHPUnit\Framework\TestCase;
use Platformsh\Client\Session\Storage\File;

class FileTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/platformsh-client-test-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        $filename = $this->dir . '/sess-test/sess-test.json';
        if (file_exists($filename)) {
            unlink($filename);
        }
        foreach ([$this->dir . '/sess-test', $this->dir] as $dir) {
            if (is_dir($dir)) {
                rmdir($dir);
            }
        }
    }

    public function testSaveAndLoad(): void
    {
        $storage = new File($this->dir);
        $this->assertSame([], $storage->load('test'));
        $storage->save('test', [
            'foo' => 'bar',
        ]);
        $this->assertSame([
            'foo' => 'bar',
        ], $storage->load('test'));
        $storage->save('test', []);
        $this->assertSame([], $storage->load('test'));
    }

    public function testLoadWaitsForExclusiveLock(): void
    {
        $storage = new File($this->dir);
        $storage->save('test', [
            'token' => 'old',
        ]);
        $filename = $this->dir . '/sess-test/sess-test.json';

        // A child process truncates the file under an exclusive lock, as
        // file_put_contents() does, and writes the new data after a delay.
        $script = <<<'PHP'
            $h = fopen($argv[1], 'c');
            flock($h, LOCK_EX);
            ftruncate($h, 0);
            echo "locked\n";
            usleep(500000);
            fwrite($h, '{"token":"new"}');
            fflush($h);
            flock($h, LOCK_UN);
            fclose($h);
            PHP;
        $process = proc_open([PHP_BINARY, '-r', $script, $filename], [
            1 => ['pipe', 'w'],
        ], $pipes);
        $this->assertIsResource($process);
        $this->assertSame("locked\n", fgets($pipes[1]));

        $data = $storage->load('test');

        fclose($pipes[1]);
        $this->assertSame(0, proc_close($process));
        $this->assertSame([
            'token' => 'new',
        ], $data);
    }
}
