<?php

namespace Platformsh\Client\Session\Storage;

use Platformsh\Client\Session\SessionInterface;

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
        $default = $this->getHomeDirectory() . '/.platformsh/.session';
        if ($this->canWrite($default)) {
            return $default;
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
     * @return string
     */
    protected function getHomeDirectory()
    {
        $home = getenv('HOME');
        if (!$home && ($userProfile = getenv('USERPROFILE'))) {
            $home = $userProfile;
        }
        if (!$home || !is_dir($home)) {
            throw new \RuntimeException('Could not determine home directory');
        }

        return $home;
    }

    /**
     * @inheritdoc
     */
    public function save(SessionInterface $session)
    {
        $data = $session->getData();
        $filename = $this->getFilename($session);
        if (empty($data)) {
            if (file_exists($filename)) {
                unlink($filename);
            }
            return true;
        }
        $this->mkDir(dirname($filename));
        $result = file_put_contents($filename, json_encode($data));
        if ($result === false) {
            throw new \Exception("Failed to save session to file: $filename");
        }
        chmod($filename, self::FILE_MODE);

        return true;
    }

    /**
     * @param SessionInterface $session
     *
     * @return string
     */
    protected function getFilename(SessionInterface $session)
    {
        $id = preg_replace('/[^\w\-]+/', '-', $session->getId());
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
    public function load(SessionInterface $session)
    {
        $filename = $this->getFilename($session);
        if (is_readable($filename)) {
            $raw = file_get_contents($filename);
            if ($raw !== false) {
                $data = json_decode($raw, true);
                $session->setData(is_array($data) ? $data : []);
            }
        }
    }
}
