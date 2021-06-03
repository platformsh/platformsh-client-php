<?php

namespace Platformsh\Client\Model\Environment;

final class BranchOptions {
    /** @var string */
    private $id;
    /** @var string|null */
    private $title;
    /** @var bool|null */
    private $clone_parent;
    /** @var string|null */
    private $type;

    public function __construct(string $id, string $title = null, bool $cloneParent = null, string $type = null)
    {
        $this->id = $id;
        $this->title = $title;
        $this->clone_parent = $cloneParent;
        $this->type = $type;
    }

    public function toArray(): array {
        $arr = [];
        foreach ($this as $key => $value) {
            if ($value !== null) {
                $arr[$key] = $value;
            }
        }
        return $arr;
    }
}
