<?php

declare(strict_types=1);

namespace Platformsh\Client\Model;

use GuzzleHttp\ClientInterface;

/**
 * A class wrapping the result of an API call.
 */
class Result extends ApiResourceBase
{
    protected string $resourceClass;

    public function __construct(array $data, $baseUrl, ClientInterface $client, string $className)
    {
        parent::__construct($data, $baseUrl, $client);
        $this->setResourceClass($className);
    }

    /**
     * @internal
     */
    public function setResourceClass(string $className): void
    {
        if (! class_exists($className)) {
            throw new \InvalidArgumentException("Class not found: {$className}");
        }

        $this->resourceClass = $className;
    }

    /**
     * Count the activities embedded in the result.
     */
    public function countActivities(): int
    {
        if (! isset($this->data['_embedded']['activities'])) {
            return 0;
        }

        return count($this->data['_embedded']['activities']);
    }

    /**
     * Get activities embedded in the result.
     *
     * A result could embed 0, 1, or many activities.
     *
     * @return Activity[]
     */
    public function getActivities(): array
    {
        if (! isset($this->data['_embedded']['activities'])) {
            return [];
        }

        $activities = [];
        foreach ($this->data['_embedded']['activities'] as $data) {
            $activities[] = new Activity($data, $this->baseUrl, $this->client);
        }

        return $activities;
    }

    /**
     * Get the entity embedded in the result.
     *
     * @throws \Exception If no entity was embedded.
     *
     * @return ApiResourceBase
     *   An instance of ApiResourceBase - the implementing class name was set
     *   when this Result was instantiated.
     */
    public function getEntity(): ApiResourceBase
    {
        if (! isset($this->data['_embedded']['entity']) || ! isset($this->resourceClass)) {
            throw new \Exception('No entity found in result');
        }

        $data = $this->data['_embedded']['entity'];
        $resourceClass = $this->resourceClass;
        return new $resourceClass($data, $this->baseUrl, $this->client);
    }

    public function update(array $values): self
    {
        throw new \BadMethodCallException('Cannot update() a Result instance directly. Perhaps use getEntity().');
    }

    public function delete(): self
    {
        throw new \BadMethodCallException('Cannot delete() a Result instance directly. Perhaps use getEntity().');
    }
}
