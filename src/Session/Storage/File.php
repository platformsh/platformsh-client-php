<?php

namespace Platformsh\Client\Session\Storage;

class File implements SessionStorageInterface
{

    const FILE_MODE = 0600;
    const DIR_MODE = 0700;

    protected $directory;

    /**
     * @param string $directory A directory where session files will be saved
     *                          (default: ~/.platformsh/.session)
     */
    public function __construct($directory = null)
    {
        $this->directory = $directory ?: $this->getHomeDirectory() . '/.platformsh/.session';
    }

    /**
     * @throws \Exception
     *
     * @return string
     */
    protected function getHomeDirectory()
    {
        $home = getenv('HOME');
        if (!$home && ($userProfile = getenv('USERPROFILE'))) {
            $home = $userProfile;
        }
        if (!$home || !is_dir($home)) {
            throw new \Exception('Could not determine home directory');
        }

        return $home;
    }

    /**
     * @inheritdoc
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
