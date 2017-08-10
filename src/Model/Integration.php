<?php

namespace Platformsh\Client\Model;

/**
 * A project integration.
 *
 * @property-read string $id
 * @property-read string $type
 */
class Integration extends Resource
{

    /** @var array */
    protected static $required = ['type'];

    /** @var array */
    protected static $types = [
      'bitbucket',
      'hipchat',
      'github',
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
}
