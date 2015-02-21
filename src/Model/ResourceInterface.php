<?php

namespace Platformsh\Client\Model;

interface ResourceInterface
{

    /**
     * Refresh the current resource.
     *
     * @param array $options
     */
    public function refresh(array $options = []);

    /**
     * @param string $rel
     *
     * @return string
     */
    public function getLink($rel);

    /**
     * @return array
     */
    public function getData();
}
