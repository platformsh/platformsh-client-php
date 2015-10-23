<?php

namespace Platformsh\Client\Model;

/**
 * A user's SSH public key.
 *
 * @deprecated
 */
class SshKey_Platform extends Resource
{

    protected static $required = ['key'];

    /**
     * @inheritdoc
     */
    protected static function checkProperty($property, $value)
    {
        if ($property === 'key' && !SshKey::validatePublicKey($value)) {
            return ["The SSH key is invalid"];
        }
        return [];
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
}
