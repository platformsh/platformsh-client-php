<?php

namespace Platformsh\Client\Model;

use GuzzleHttp\ClientInterface;

/**
 * A record establishing a user's access to a Platform.sh environment.
 *
 * @property-read string $user The user UUID
 * @property-read string $role The user's role
 * @property-read string $project The project ID
 * @property-read string $environment The environment ID
 */
class EnvironmentAccess extends Resource
{

    /** @var array */
    protected static $required = ['user', 'role'];

    const ROLE_ADMIN = 'admin';
    const ROLE_VIEWER = 'viewer';
    const ROLE_CONTRIBUTOR = 'contributor';

    public static $roles = [self::ROLE_ADMIN, self::ROLE_VIEWER, self::ROLE_CONTRIBUTOR];

    /**
     * @inheritdoc
     */
    protected static function checkProperty($property, $value)
    {
        $errors = [];
        if ($property === 'role' && !in_array($value, static::$roles)) {
            $errors[] = "Invalid environment role: '$value'";
        }

        return $errors;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Exception if the expected activity is not returned.
     *
     * @return Activity
     */
    public static function create(array $body, $collectionUrl, ClientInterface $client)
    {
        if ($errors = static::checkNew($body)) {
            $message = "Cannot create resource due to validation error(s): " . implode('; ', $errors);
            throw new \InvalidArgumentException($message);
        }

        $request = $client->createRequest('post', $collectionUrl, ['json' => $body]);
        $data = self::send($request, $client);

        if (!isset($data['_embedded']['activities'][0])) {
            throw new \Exception('Expected activity not found');
        }

        return Activity::wrap($data['_embedded']['activities'][0], $collectionUrl, $client);
    }

    /**
     * {@inheritdoc}
     *
     * @return Activity
     */
    public function update(array $values)
    {
        if ($errors = $this->checkUpdate($values)) {
            $message = "Cannot update resource due to validation error(s): " . implode('; ', $errors);
            throw new \InvalidArgumentException($message);
        }

        $request = $this->client->createRequest('patch', $this->getUri(), ['json' => $values]);
        $data = $this->send($request, $this->client);
        if (isset($data['_embedded']['entity'])) {
            $data = $data['_embedded']['entity'];
            $this->setData($data + ['_full' => true]);
        }

        if (!isset($data['_embedded']['activities'][0])) {
            throw new \Exception('Expected activity not found');
        }

        return Activity::wrap($data['_embedded']['activities'][0], $this->baseUrl, $this->client);
    }
}
