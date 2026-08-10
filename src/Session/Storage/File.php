<?php

declare(strict_types=1);

namespace Platformsh\Client\Session\Storage;

class File implements SessionStorageInterface
{
    public const FILE_MODE = 0600;

    public const DIR_MODE = 0700;

    protected string $directory;

    /**
     * @param string|null $directory
     *   A writable directory where session files will be saved. Leave null
     *   to use the default.
     */
    public function __construct(?string $directory = null)
    {
        $this->directory = $directory ?: $this->getDefaultDirectory();
    }

    /**
     * @throws \Exception
     */
    public function save(string $sessionId, array $data): void
    {
        $filename = $this->getFilename($sessionId);
        if (empty($data)) {
            if (file_exists($filename)) {
                unlink($filename);
            }
            return;
        }
        $this->mkDir(dirname($filename));
        $result = file_put_contents($filename, json_encode($data), LOCK_EX);
        if ($result === false) {
            throw new \Exception("Failed to save session to file: {$filename}");
        }
        chmod($filename, self::FILE_MODE);
    }

    public function load(string $sessionId): array
    {
        $data = [];
        $filename = $this->getFilename($sessionId);
        if (is_readable($filename)) {
            $raw = file_get_contents($filename);
            if ($raw !== false) {
                $data = json_decode($raw, true);
            }
        }

        return is_array($data) ? $data : [];
    }

    /**
     * Get the default directory for session files.
     */
    protected function getDefaultDirectory(): string
    {
        // Default to ~/.platformsh/.session, but if it's not writable, fall
        // back to the temporary directory.
        $home = $this->getHomeDirectory();
        if ($home !== null) {
            $default = rtrim($home, '/') . '/.platformsh/.session';
            if ($this->canWrite($default)) {
                return $default;
            }
        }
        $temp = sys_get_temp_dir() . '/.platformsh-client/.session';
        if ($this->canWrite($temp)) {
            return $temp;
        }

        throw new \RuntimeException('Unable to find a writable session storage directory');
    }

    /**
     * Tests whether a file path is writable (even if it doesn't exist).
     */
    protected function canWrite(string $path): bool
    {
        if (is_writable($path)) {
            return true;
        }

        $current = $path;
        while (! file_exists($current) && ($parent = dirname($current)) && $parent !== $current) {
            if (is_writable($parent)) {
                return true;
            }
            $current = $parent;
        }

        return false;
    }

    /**
     * Finds the user's home directory.
     */
    protected function getHomeDirectory(): ?string
    {
        $home = getenv('HOME');
        if (! $home && ($userProfile = getenv('USERPROFILE'))) {
            $home = $userProfile;
        }
        if (! $home || ! is_dir($home)) {
            return null;
        }

        return $home;
    }

    protected function getFilename(string $sessionId): string
    {
        $id = preg_replace('/[^\w\-]+/', '-', $sessionId);
        $dir = $this->getDirectory();

        return "{$dir}/sess-{$id}/sess-{$id}.json";
    }

    protected function getDirectory(): string
    {
        return rtrim($this->directory, '/');
    }

    /**
     * Create a directory.
     *
     *@throws \Exception
     */
    protected function mkDir(string $dir): void
    {
        if (! file_exists($dir)) {
            mkdir($dir, self::DIR_MODE, true);
            chmod($dir, self::DIR_MODE);
        }
        if (! is_dir($dir)) {
            throw new \Exception("Failed to create directory: {$dir}");
        }
    }
}
