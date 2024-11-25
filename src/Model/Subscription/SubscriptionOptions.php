<?php

declare(strict_types=1);
/** @noinspection PhpUnusedPrivateFieldInspection */

namespace Platformsh\Client\Model\Subscription;

final class SubscriptionOptions
{
    private ?string $project_region;

    private ?string $project_title;

    private ?string $default_branch;

    private ?string $options_url;

    private ?array $options_custom;

    private ?string $plan;

    private ?int $environments;

    private ?int $storage;

    private ?string $owner;

    /**
     * @deprecated This is no longer supported. Poll the subscription instead of submitting a callback.
     */
    private ?array $activation_callback;

    private ?string $organization_id;

    public static function fromArray(array $options): self
    {
        $obj = new self();
        foreach ($options as $key => $value) {
            if (\property_exists($obj, $key)) {
                $obj->{$key} = $value;
            } else {
                throw new \InvalidArgumentException('Unknown property: ' . $key);
            }
        }
        return $obj;
    }

    public function toArray(): array
    {
        $arr = [];
        foreach ($this as $key => $value) {
            if ($value !== null && $value !== 'organization_id') {
                $arr[$key] = $value;
            }
        }
        return $arr;
    }

    public function organizationId(): ?string
    {
        return $this->organization_id;
    }
}
