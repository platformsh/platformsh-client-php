<?php

namespace Platformsh\Client\Session\Storage;

class File implements SessionStorageInterface
{

    const FILE_MODE = 0600;
    const DIR_MODE = 0700;

    protected $directory;

    /**
     * @param string|null $directory
     *   A writable directory where session files will be saved. Leave null
     *   to use the default.
     */
    public function __construct($directory = null)
    {
        $this->directory = $directory ?: $this->getDefaultDirectory();
    }

    /**
     * Get the default directory for session files.
     *
     * @return string
     */
    protected function getDefaultDirectory()
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
     *
     * @param string $path
     *
     * @return bool
     */
    protected function canWrite($path)
    {
        if (is_writable($path)) {
            return true;
        }

        $current = $path;
        while (!file_exists($current) && ($parent = dirname($current)) && $parent !== $current) {
            if (is_writable($parent)) {
                return true;
            }
            $current = $parent;
        }

        return false;
    }

    /**
     * Finds the user's home directory.
     *
     * @return string|null
     */
    protected function getHomeDirectory()
    {
        $home = getenv('HOME');
        if (!$home && ($userProfile = getenv('USERPROFILE'))) {
            $home = $userProfile;
        }
        if (!$home || !is_dir($home)) {
            return null;
        }

        return $home;
    }

    /**
     * @inheritdoc
     * @throws \Exception
     */
    public function save($sessionId, array $data)
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
            throw new \Exception("Failed to save session to file: $filename");
        }
        chmod($filename, self::FILE_MODE);
    }

    /**
     * @param string $sessionId
     *
     * @return string
     */
    protected function getFilename($sessionId)
    {
        $id = preg_replace('/[^\w\-]+/', '-', $sessionId);
        $dir = $this->getDirectory();

        return "$dir/sess-$id/sess-$id.json";
    }

    /**
     * @return string
     */
    protected function getDirectory()
    {
        return rtrim($this->directory, '/');
    }

    /**
     * Create a directory.
     *
     * @throws \Exception
     *
     * @param string $dir
     */
    protected function mkDir($dir)
    {
        if (!file_exists($dir)) {
            mkdir($dir, self::DIR_MODE, true);
            chmod($dir, self::DIR_MODE);
        }
        if (!is_dir($dir)) {
            throw new \Exception("Failed to create directory: $dir");
        }
    }

    /**
     * @inheritdoc
     */
    public function load($sessionId)
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
}
