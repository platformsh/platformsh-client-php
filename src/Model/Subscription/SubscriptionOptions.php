<?php

declare(strict_types=1);
/** @noinspection PhpUnusedPrivateFieldInspection */

namespace Platformsh\Client\Model\Subscription;

final class SubscriptionOptions
{
    /**
     * @var string|null
     */
    private $project_region;

    /**
     * @var string|null
     */
    private $project_title;

    /**
     * @var string|null
     */
    private $default_branch;

    /**
     * @var string|null
     */
    private $options_url;

    /**
     * @var array|null
     */
    private $options_custom;

    /**
     * @var string|null
     */
    private $plan;

    /**
     * @var int|null
     */
    private $environments;

    /**
     * @var int|null
     */
    private $storage;

    /**
     * @var string|null
     */
    private $owner;

    /**
     * @var array|null
     * @deprecated This is no longer supported. Poll the subscription instead of submitting a callback.
     */
    private $activation_callback;

    /**
     * @var string|null
     */
    private $organization_id;

    /**
     * @return SubscriptionOptions
     */
    public static function fromArray(array $options)
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

    /**
     * @return array
     */
    public function toArray()
    {
        $arr = [];
        foreach ($this as $key => $value) {
            if ($value !== null && $value !== 'organization_id') {
                $arr[$key] = $value;
            }
        }
        return $arr;
    }

    /**
     * @return string|null
     */
    public function organizationId()
    {
        return $this->organization_id;
    }
}
