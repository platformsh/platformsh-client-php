<?php

namespace Platformsh\Client\OAuth2;

use CommerceGuys\Guzzle\Oauth2\GrantType\PasswordCredentials;

class PasswordCredentialsWithTfa extends PasswordCredentials
{
    /**
     * @var string|int
     */
    protected $totp;

    /**
     * @param string|int $code
     */
    public function setTotp($code)
    {
        $this->totp = $code;
    }

    /**
     * {@inheritdoc}
     */
    public function getAdditionalOptions() {
        if ($this->totp !== null) {
            return ['headers' => ['X-Drupal-TFA' => $this->totp]];
        }

        return null;
    }
}
