<?php

namespace Platformsh\Client\Model;

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
     */
    public function update(array $values)
    {
        # Environment access resources don't contain an 'edit' operation link.
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

        return $data;
    }
}
