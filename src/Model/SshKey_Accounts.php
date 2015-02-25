<?php

namespace Platformsh\Client\Model;

/**
 * @deprecated
 */
class SshKey_Accounts extends Resource
{

    /** @var array */
    protected static $required = ['value'];

    /**
     * @inheritdoc
     */
    public static function check(array $data)
    {
        $errors = parent::check($data);
        if (isset($data['value']) && !SshKey::validatePublicKey($data['value'])) {
            $errors[] = "The SSH key is invalid";
        }

        return $errors;
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
    public function getUri($absolute = false)
    {
        $relative = 'ssh_keys/' . $this->data['key_id'];
        $base = $this->client->getBaseUrl();
        return $absolute ? $base . $relative : $relative;
    }
}
