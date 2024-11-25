<?php

declare(strict_types=1);

namespace Platformsh\Client\Model;

/**
 * A user's SSH public key.
 *
 * @property-read string $title
 * @property-read int    $key_id
 * @property-read string $fingerprint
 */
class SshKey extends ApiResourceBase
{
    protected static $required = ['value'];

    protected static $allowedAlgorithms = [
        'ssh-rsa',
        'ssh-dsa',
        'ssh-ed25519',
        'ecdsa-sha2-nistp256',
        'ecdsa-sha2-nistp384',
        'ecdsa-sha2-nistp521',
    ];

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
        if (! strpos($value, ' ')) {
            return false;
        }
        list($type, $key) = explode(' ', $value, 3);
        if (! in_array($type, static::$allowedAlgorithms, true) || base64_decode($key, true) === false) {
            return false;
        }

        return true;
    }

    /**
     * @throws \BadMethodCallException
     */
    public function update(array $values)
    {
        throw new \BadMethodCallException('Update is not implemented for SSH keys');
    }

    public function getUri($absolute = true)
    {
        // Work around absence of HAL links in the current API.
        return $this->baseUrl;
    }

    protected static function checkProperty($property, $value)
    {
        if ($property === 'value' && ! self::validatePublicKey($value)) {
            return ['The SSH key is invalid'];
        }
        return [];
    }
}
