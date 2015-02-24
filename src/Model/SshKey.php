<?php

namespace Platformsh\Client\Model;

class SshKey extends Resource
{

    /** @var array */
    protected static $required = ['key'];

    /**
     * @inheritdoc
     */
    public static function check(array $data)
    {
        $errors = parent::check($data);
        if (isset($data['key']) && !self::validatePublicKey($data['key'])) {
            $errors[] = "The SSH key is invalid";
        }

        return $errors;
    }

    /**
     * Validate an SSH public key.
     *
     * @param string $value
     *
     * @return bool
     */
    public static function validatePublicKey($value)
    {
        $value = preg_replace('/\s+/', ' ', $value);
        if (!strpos($value, ' ')) {
            return false;
        }
        list($type, $key) = explode(' ', $value, 2);
        if (!in_array($type, ['ssh-rsa', 'ssh-dsa']) || base64_decode($key, true) === false) {
            return false;
        }

        return true;
    }
}
