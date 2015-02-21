<?php

namespace Platformsh\Client\Session\Storage;

use Platformsh\Client\Session\SessionInterface;

class File implements SessionStorageInterface
{

    const FILE_MODE = 0600;
    const DIR_MODE = 0700;

    protected $directory;

    /**
     * @param string $directory A directory where session files will be saved
     *                          (default: ~/.platformsh)
     */
    public function __construct($directory = null)
    {
        $this->directory = $directory ?: $this->getHomeDirectory() . '/.platformsh';
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
    public function save(SessionInterface $session)
    {
        $data = $session->getData();
        $filename = $this->getFilename($session);
        if (!$data && file_exists($filename)) {
            return unlink($filename);
        }
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

        return "$dir/sess-$id.json";
    }

    /**
     * @throws \Exception
     *
     * @return string
     */
    protected function getDirectory()
    {
        $dir = $this->directory;
        if (!file_exists($dir)) {
            mkdir($dir, self::DIR_MODE, true);
            chmod($dir, self::DIR_MODE);
        }
        if (!is_dir($dir)) {
            throw new \Exception("Invalid session directory: $dir");
        }

        return rtrim($dir, '/');
    }

    /**
     * @inheritdoc
     */
    public function load(SessionInterface $session)
    {
        $filename = $this->getFilename($session);
        if (file_exists($filename)) {
            $raw = file_get_contents($filename);
            if ($raw === false) {
                throw new \Exception("Failed to read file: $filename");
            }
            $data = json_decode($raw, true);
            $session->setData($data);
        }
    }
}
