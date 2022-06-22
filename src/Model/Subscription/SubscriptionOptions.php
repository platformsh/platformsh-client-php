<?php /** @noinspection PhpUnusedPrivateFieldInspection */

namespace Platformsh\Client\Model\Subscription;

final class SubscriptionOptions {
    /** @var string|NULL */
    private $project_region;
    /** @var string|NULL */
    private $project_title;
    /** @var string|NULL */
    private $default_branch;
    /** @var string|NULL */
    private $options_url;
    /** @var string|NULL */
    private $plan;
    /** @var int|NULL */
    private $environments;
    /** @var int|NULL */
    private $storage;
    /** @var string|NULL */
    private $owner;

    /**
     * @var array|NULL
     * @deprecated This is no longer supported. Poll the subscription instead of submitting a callback.
     */
    private $activation_callback;

    /** @var string|NULL */
    private $organization_id;

    /**
     * @param array $options
     *
     * @return SubscriptionOptions
     */
    public static function fromArray(array $options)
    {
        $obj = new self();
        foreach ($options as $key => $value) {
            if (\property_exists($obj, $key)) {
                $obj->$key = $value;
            } else {
                throw new \InvalidArgumentException('Unknown property: ' . $key);
            }
        }
        return $obj;
    }

    /** @return array */
    public function toArray() {
        $arr = [];
        foreach ($this as $key => $value) {
            if ($value !== null && $value !== 'organization_id') {
                $arr[$key] = $value;
            }
        }
        return $arr;
    }

    /** @return string|NULL */
    public function organizationId() {
        return $this->organization_id;
    }
}
