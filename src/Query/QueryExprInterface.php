<?php
namespace McnHealthcare\ODM\Dynamodb\Query;

/**
 * Interface QueryExprInterface
 * Public API for query experssions.
 */
interface QueryExprInterface
{
    /**
     * Gets map of field name and indexable flag pairs.
     *
     * @return array
     */
    public function getFields(): array;

    /**
     * Gets expression string.
     *
     * @return array
     */
    public function expr(): string;
}
