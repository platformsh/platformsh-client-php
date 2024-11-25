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
    protected static array $required = ['value'];

    protected static array $allowedAlgorithms = [
        'ssh-rsa',
        'ssh-dsa',
        'ssh-ed25519',
        'ecdsa-sha2-nistp256',
        'ecdsa-sha2-nistp384',
        'ecdsa-sha2-nistp521',
    ];

    /**
     * Validate an SSH public key.
     */
    public static function validatePublicKey(string $value): bool
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
    public function update(array $values): Result
    {
        throw new \BadMethodCallException('Update is not implemented for SSH keys');
    }

    public function getUri(bool $absolute = true): string
    {
        // Work around absence of HAL links in the current API.
        return $this->baseUrl;
    }

    protected static function checkProperty(string $property, mixed $value): array
    {
        if ($property === 'value' && ! self::validatePublicKey($value)) {
            return ['The SSH key is invalid'];
        }
        return [];
    }
}
