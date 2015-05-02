<?php

namespace Platformsh\Client\Model;

/**
 * A user's SSH public key.
 *
 * @property-read string $title
 * @property-read int    $key_id
 * @property-read string $fingerprint
 */
class SshKey extends Resource
{

    protected static $required = ['value'];

    /**
     * @inheritdoc
     */
    protected static function checkProperty($property, $value)
    {
        if ($property === 'value' && !self::validatePublicKey($value)) {
            return ["The SSH key is invalid"];
        }
        return [];
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
        list($type, $key) = explode(' ', $value, 3);
        if (!in_array($type, ['ssh-rsa', 'ssh-dsa']) || base64_decode($key, true) === false) {
            return false;
        }

        return true;
    }

    /**
     * @inheritdoc
     *
     * @throws \BadMethodCallException
     */
    public function update(array $values)
    {
        throw new \BadMethodCallException('Update is not implemented for SSH keys');
    }

    /**
     * @inheritdoc
     */
    public function getUri($absolute = true)
    {
        // Work around absence of HAL links in the current API.
        return $this->baseUrl;
    }
}
