<?php

namespace Platformsh\Client\Model\Team;

use GuzzleHttp\ClientInterface;
use Platformsh\Client\Model\Resource;
use Platformsh\Client\Model\Result;

/**
 * @property-read string $id
 * @property-read string $organization_id
 * @property-read string $label
 * @property-read string[] $project_permissions
 * @property-read array{member_count: int, project_count: int} $counts
 * @property-read string $created_at
 * @property-read string $updated_at
 */
class Team extends Resource
{
    protected static $collectionItemsKey = 'items';

    /**
     * {@inheritdoc}
     *
     * @internal Use Organization::createTeam() to create a team.
     *
     * @see \Platformsh\Client\Model\Organization\Organization::createTeam()
     *
     * @return static
     */
    public static function create(array $body, $collectionUrl, ClientInterface $client)
    {
        $result = parent::create($body, $collectionUrl, $client);
        return new static($result->getData(), $collectionUrl, $client);
    }

    public function update(array $values)
    {
        // A successful PATCH on this resource returns an empty 204 result.
        $this->client->patch($this->getUri(), ['json' => $values]);

        // TODO this may not be exactly the right merge semantics in general, but it works in this case as this resource only has 2 writable keys
        $this->data = array_replace_recursive($this->data, $values);

        // TODO this method should probably be deprecated as a Result is not very useful here
        $resultData = [];
        $resultData['_embedded']['entity'] = $this->data;

        return new Result($resultData, $this->baseUrl, $this->client, static::class);
    }

    /**
     * Returns a team member, by user ID.
     *
     * @return TeamMember|false
     */
    public function getMember($userId)
    {
        return TeamMember::get($userId, $this->getUri() . '/members', $this->client);
    }
}
