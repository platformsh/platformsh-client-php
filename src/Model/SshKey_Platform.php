<?php

namespace Platformsh\Client\Model;

/**
 * A user's SSH public key.
 *
 * @deprecated
 */
class SshKey_Platform extends Resource
{

    /** @var array */
    protected static $required = ['key'];

    /**
     * @inheritdoc
     */
    public static function check(array $data)
    {
        $errors = parent::check($data);
        if (isset($data['key']) && !SshKey::validatePublicKey($data['key'])) {
            $errors[] = "The SSH key is invalid";
        }

        return $errors;
    }
}
