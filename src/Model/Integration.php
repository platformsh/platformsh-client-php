<?php

namespace Platformsh\Client\Model;

use GuzzleHttp\Exception\BadResponseException;

/**
 * A project integration.
 *
 * @property-read string $id
 * @property-read string $type
 */
class Integration extends ApiResourceBase
{

    /** @var array */
    protected static $required = ['type'];

    /** @var array */
    protected static $types = [
      'bitbucket',
      'hipchat',
      'github',
      'gitlab',
      'webhook',
      'health.email',
      'health.pagerduty',
      'health.slack',
    ];

    /**
     * @inheritdoc
     */
    protected static function checkProperty($property, $value)
    {
        $errors = [];
        if ($property === 'type' && !in_array($value, self::$types)) {
            $errors[] = "Invalid type: '$value'";
        }

        return $errors;
    }

    /**
     * Trigger the integration's web hook.
     *
     * Normally the external service should do this in response to events, but
     * it may be useful to trigger the hook manually in certain cases.
     */
    public function triggerHook()
    {
        $hookUrl = $this->getLink('#hook');
        $options = [];

        // The API needs us to send an empty JSON object.
        $options['json'] = new \stdClass();

        // Switch off authentication for this request (none is required).
        $options['auth'] = null;

        $this->sendRequest($hookUrl, 'post', $options);
    }

    /**
     * Validate the integration via the API.
     *
     * @throws \RuntimeException If an unexpected error occurs.
     *
     * @return array
     *   An array of errors, as returned by the API. An empty array indicates
     *   the integration is valid.
     */
    public function validate()
    {
        try {
            $this->runOperation('validate', 'post');

            return [];
        } catch (BadResponseException $e) {
            $response = $e->getResponse();
            if ($response && $response->getStatusCode() === 400) {
                $response->getBody()->seek(0);
                $data = $response->json();
                if (isset($data['detail']) && is_array($data['detail'])) {
                    return $data['detail'];
                }
            }
            throw $e;
        }
    }
}
