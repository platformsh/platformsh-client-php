<?php

namespace Platformsh\Client\Model;

/**
 * A user's SSH public key.
 *
 * @deprecated Use the SSH key methods in PlatformClient.
 *
 * @see \Platformsh\Client\PlatformClient::addSshKey()
 * @see \Platformsh\Client\PlatformClient::getSshKey()
 * @see \Platformsh\Client\PlatformClient::getSshKeys()
 */
class SshKey_Platform extends ApiResourceBase
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
