<?php
namespace McnHealthcare\ODM\Dynamodb\Ut;

/**
 * Interface MockContainer
 * Mockup for a container to get mocked values by name from mock domain.
 */
interface MockContainer
{
    /**
     * Gets a mocked return value by name.
     *
     * @param string $itemName Name of item to get.
     */
    public function get(string $itemName);
}
