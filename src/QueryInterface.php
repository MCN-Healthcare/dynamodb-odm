<?php
namespace McnHealthcare\ODM\Dynamodb;

use Psr\Log\LoggerInterface;
use McnHealthcare\ODM\Dynamodb\ItemManagerInterface;
use McnHealthcare\ODM\Dynamodb\Query\QueryExprInterface;
use McnHealthcare\ODM\Dynamodb\Query\QueryExprFactoryInterface;

/**
 * Interface QueryInterface
 * Public api for the query class.
 */
interface QueryInterface
{
    /**
     * Perform a dynamo db query.
     */
    const QUERY_OP = 'query';

    /**
     * Perform a dynamo db scan.
     */
    const SCAN_OP = 'scan';

    /**
     * Gets an expression builder.
     *
     * @return QueryExprFactoryInterface
     */
    public function expr(): QueryExprFactoryInterface;

    /**
     * Execute the query.
     *
     * @param array $params Optional map of query parameters.
     *
     * @return QueryInterface ($self).
     */
    public function execute(array $params = []): QueryInterface;

    /**
     * Starts a new query.
     *
     * @param string $itemClass Entity class the query is for.
     *
     * @return QueryInterface ($self).
     */
    public function from(string $itemClass): QueryInterface;

    /**
     * Gets query results.
     *
     * @return array
     */
    public function getResults(): array;

    /**
     * Sets fetch limit.
     *
     * @param int $limit Number of results per odm fetch.
     *
     * @return QueryInterface ($self).
     */
    public function limit(int $limit): QueryInterface;

    /**
     * Adds a query parameter.
     *
     * @param string $name Field name with ':' prefix.
     * @param mixed $value Parameter value.
     *
     * @return QueryInterface ($self).
     */
    public function parameter(string $name, $value): QueryInterface;

    /**
     * Prepares the query for execution.
     *
     * @return QueryInterface ($self).
     */
    public function prepare(): QueryInterface;

    /**
     * Adds criteria to the query.
     *
     * @param QueryExprInterface $expr Query expression.
     *
     * @return QueryInterface
     */
    public function where(QueryExprInterface $expr): QueryInterface;
}
