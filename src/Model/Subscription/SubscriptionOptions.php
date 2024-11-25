<?php

declare(strict_types=1);

namespace Platformsh\Client\Model\Subscription;

final readonly class SubscriptionOptions
{
    private array $options;

    private function __construct(array $options)
    {
        $this->options = $options;
    }

    /**
     * @param array{
     *     project_region: ?string,
     *     project_title: ?string,
     *     default_branch: ?string,
     *     options_url: ?string,
     *     options_custom: ?array,
     *     plan: ?string,
     *     environments: ?int,
     *     storage: ?int,
     *     organization_id: ?string,
     * } $options
     */
    public static function fromArray(array $options): self
    {
        return new self($options);
    }

    public function toArray(): array
    {
        return $this->options;
    }

    public function organizationId(): ?string
    {
        return $this->options['organization_id'] ?? null;
    }
}
