<?php

declare(strict_types=1);

namespace Platformsh\Client\Model;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Utils;
use Platformsh\Client\Exception\ApiResponseException;
use Platformsh\Client\Model\Ref\Resolver;

/**
 * Represents a collection of items.
 *
 * Automatically resolves references when fetching the next or previous page.
 */
class Collection
{
    private Resolver $resolver;

    public function __construct(
        private array $data,
        private readonly ClientInterface $client,
        private readonly string $baseUrl
    ) {
        $this->resolver = new Resolver($client, $baseUrl);
    }

    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Returns the total count of items in a collection (across all pages), if available.
     */
    public function getTotalCount(): ?int
    {
        return isset($this->data['count']) ? (int) $this->data['count'] : null;
    }

    /**
     * @internal
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function hasNextPage(): bool
    {
        return ! empty($this->data['_links']['next']['href']);
    }

    public function fetchNextPage(): null|static
    {
        if (empty($this->data['_links']['next']['href'])) {
            return null;
        }
        return $this->doFetchPage($this->data['_links']['next']['href']);
    }

    public function getNextPageUrl(): ?string
    {
        if (empty($this->data['_links']['next']['href'])) {
            return null;
        }
        return $this->data['_links']['next']['href'];
    }

    public function hasPreviousPage(): bool
    {
        return ! empty($this->data['_links']['previous']['href']);
    }

    public function fetchPreviousPage(): null|static
    {
        if (empty($this->data['_links']['previous']['href'])) {
            return null;
        }
        return $this->doFetchPage($this->data['_links']['previous']['href']);
    }

    public function getPreviousPageUrl(): ?string
    {
        if (empty($this->data['_links']['previous']['href'])) {
            return null;
        }
        return $this->data['_links']['previous']['href'];
    }

    private function doFetchPage($url): static
    {
        $request = new Request('GET', $url);
        try {
            $response = $this->client->send($request);
            $data = Utils::jsonDecode((string) $response->getBody(), true);
            $data = $this->resolver->resolveReferences($data);

            return new static($data, $this->client, $this->baseUrl);
        } catch (BadResponseException $e) {
            throw ApiResponseException::create($e->getRequest(), $e->getResponse());
        }
    }
}
