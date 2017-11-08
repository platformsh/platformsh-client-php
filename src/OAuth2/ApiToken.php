<?php

namespace Platformsh\Client\OAuth2;

use Sainsburys\Guzzle\Oauth2\GrantType\GrantTypeBase;
use Sainsburys\Guzzle\Oauth2\GrantType\RefreshTokenGrantTypeInterface;

/**
 * Extends the RefreshToken grant type to support API tokens.
 *
 * API tokens are exchanged for access tokens in exactly the same way as
 * refresh tokens: the only differences are the grant type name and the JSON
 * property name for the token (both 'api_token' instead of 'refresh_token').
 */
class ApiToken extends GrantTypeBase implements RefreshTokenGrantTypeInterface
{
    protected $grantType = 'api_token';

    /**
     * @inheritdoc
     */
    protected function getDefaults()
    {
        return parent::getDefaults() + ['api_token' => ''];
    }

    /**
     * @inheritdoc
     */
    public function setRefreshToken($refreshToken)
    {
        $this->config['refresh_token'] = $refreshToken;
    }

    /**
     * @inheritdoc
     */
    public function hasRefreshToken()
    {
        return !empty($this->config['api_token']);
    }

    /**
     * @inheritdoc
     */
    public function getToken()
    {
        unset($this->config['refresh_token']);
        if (!$this->hasRefreshToken()) {
            throw new \RuntimeException("API token not available");
        }

        return parent::getToken();
    }
}
