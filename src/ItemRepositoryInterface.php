<?php
namespace McnHealthcare\ODM\Dynamodb;

use McnHealthcare\ODM\Dynamodb\Helpers\Index;

/**
 * Interface DynamoDbOdmRepository
 * Base public API for a DynamoDB ODM repository.
 */
interface ItemRepositoryInterface
{

    /**
     * Performs get for a group of keys.
     *
     * @param mixed $groupOfKeys
     * @param bool $isConsistentRead
     *
     * @return array
     */
    public function batchGet($groupOfKeys, $isConsistentRead = false);

    /**
     * Clear list of managed items.
     */
    public function clear();

    /**
     * Unmanages an instance of an item.
     *
     * @param $obj
     */
    public function detach($obj);

    /**
     * Flush managed items to db.
     *
     * @throws \Doctrine\Common\Annotations\AnnotationException
     * @throws \ReflectionException
     */
    public function flush();

    /**
     * Gets item for key.
     *
     * @param mixed $keys
     * @param bool $isConsistentRead
     *
     * @return mixed|object|null
     */
    public function get($keys, $isConsistentRead = false);

    /**
     * Performs a complex query.
     *
     * @param callable $callback
     * @param mixed $hashKey
     * @param mixed $hashKeyValues
     * @param mixed $rangeConditions
     * @param array $params
     * @param mixed $indexName
     * @param string $filterExpression
     * @param int $evaluationLimit
     * @param bool $isConsistentRead
     * @param bool $isAscendingOrder
     * @param int $concurrency
     */
    public function multiQueryAndRun(
        callable $callback,
        $hashKey,
        $hashKeyValues,
        $rangeConditions,
        array $params,
        $indexName,
        $filterExpression = '',
        $evaluationLimit = 30,
        $isConsistentRead = false,
        $isAscendingOrder = true,
        $concurrency = 10
    );

    /**
     * Gets result count for a complex query.
     *
     * @param mixed $hashKey
     * @param mixed $hashKeyValues
     * @param mixed $rangeConditions
     * @param array $params
     * @param mixed $indexName
     * @param string $filterExpression
     * @param bool $isConsistentRead
     * @param int $concurrency
     *
     * @return int
     */
    public function multiQueryCount(
        $hashKey,
        $hashKeyValues,
        $rangeConditions,
        array $params,
        $indexName,
        $filterExpression = '',
        $isConsistentRead = false,
        $concurrency = 10
    );

    /**
     * Performs parellel queries.
     *
     * @param mixed $parallel
     * @param callable $callback
     * @param string $conditions
     * @param array $params
     * @param bool $indexName
     * @param bool $isConsistentRead
     * @param bool $isAscendingOrder
     */
    public function parallelScanAndRun(
        $parallel,
        callable $callback,
        $conditions = '',
        array $params = [],
        $indexName = Index::PRIMARY_INDEX,
        $isConsistentRead = false,
        $isAscendingOrder = true
    );

    /**
     * Makes item a managed item.
     *
     * @param $obj
     */
    public function persist($obj);

    /**
     * Persist for the Activity Logger
     *
     * @param $obj
     */
    public function persistLoggable($obj);

    /**
     * Performs a query.
     *
     * @param mixed $conditions
     * @param array $params
     * @param bool $indexName
     * @param string $filterExpression
     * @param null $lastKey
     * @param int $evaluationLimit
     * @param bool $isConsistentRead
     * @param bool $isAscendingOrder
     *
     * @return array
     */
    public function query(
        $conditions,
        array $params,
        $indexName = Index::PRIMARY_INDEX,
        $filterExpression = '',
        &$lastKey = null,
        $evaluationLimit = 30,
        $isConsistentRead = false,
        $isAscendingOrder = true
    );

    /**
     * Queries all items.
     *
     * @param string $conditions
     * @param array $params
     * @param bool $indexName
     * @param string $filterExpression
     * @param bool $isConsistentRead
     * @param bool $isAscendingOrder
     *
     * @return \SplDoublyLinkedList
     */
    public function queryAll(
        $conditions = '',
        array $params = [],
        $indexName = Index::PRIMARY_INDEX,
        $filterExpression = '',
        $isConsistentRead = false,
        $isAscendingOrder = true
    );

    /**
     * Performs a query.
     *
     * @param callable $callback
     * @param string $conditions
     * @param array $params
     * @param bool $indexName
     * @param string $filterExpression
     * @param bool $isConsistentRead
     * @param bool $isAscendingOrder
     */
    public function queryAndRun(
        callable $callback,
        $conditions = '',
        array $params = [],
        $indexName = Index::PRIMARY_INDEX,
        $filterExpression = '',
        $isConsistentRead = false,
        $isAscendingOrder = true
    );

    /**
     * Query results count.
     *
     * @param mixed $conditions
     * @param array $params
     * @param bool $indexName
     * @param string $filterExpression
     * @param bool $isConsistentRead
     *
     * @return array|bool|int
     */
    public function queryCount(
        $conditions,
        array $params,
        $indexName = Index::PRIMARY_INDEX,
        $filterExpression = '',
        $isConsistentRead = false
    );

    /**
     * Reload entity from db.
     *
     * @param mixed $obj
     * @param bool $persistIfNotManaged
     */
    public function refresh($obj, $persistIfNotManaged = false);

    /**
     * Flags item to be removed ferom db on next flush.
     *
     * @param $obj
     */
    public function remove($obj);

    /**
     * Remove all items.
     *
     * @throws \Doctrine\Common\Annotations\AnnotationException
     * @throws \ReflectionException
     */
    public function removeAll();

    /**
     * Remove item(s) by primary key.
     *
     * @param mixed $keys
     */
    public function removeById($keys);

    /**
     * Perform a scan query.
     *
     * @param string $conditions
     * @param array $params
     * @param bool $indexName
     * @param null $lastKey
     * @param int  $evaluationLimit
     * @param bool $isConsistentRead
     * @param bool $isAscendingOrder
     *
     * @return array
     */
    public function scan(
        $conditions = '',
        array $params = [],
        $indexName = Index::PRIMARY_INDEX,
        &$lastKey = null,
        $evaluationLimit = 30,
        $isConsistentRead = false,
        $isAscendingOrder = true
    );

    /**
     * Perform a scan all query.
     *
     * @param string $conditions
     * @param array $params
     * @param bool $indexName
     * @param bool $isConsistentRead
     * @param bool $isAscendingOrder
     * @param int $parallel
     *
     * @return \SplDoublyLinkedList
     */
    public function scanAll(
        $conditions = '',
        array $params = [],
        $indexName = Index::PRIMARY_INDEX,
        $isConsistentRead = false,
        $isAscendingOrder = true,
        $parallel = 1
    );

    /**
     * Performs a scan query.
     *
     * @param callable $callback
     * @param string $conditions
     * @param array $params
     * @param bool $indexName
     * @param bool $isConsistentRead
     * @param bool $isAscendingOrder
     * @param int $parallel
     */
    public function scanAndRun(
        callable $callback,
        $conditions = '',
        array $params = [],
        $indexName = Index::PRIMARY_INDEX,
        $isConsistentRead = false,
        $isAscendingOrder = true,
        $parallel = 1
    );

    /**
     * Gets count of scan results.
     *
     * @param string $conditions
     * @param array $params
     * @param bool $indexName
     * @param bool $isConsistentRead
     * @param int $parallel
     *
     * @return int
     */
    public function scanCount(
        $conditions = '',
        array $params = [],
        $indexName = Index::PRIMARY_INDEX,
        $isConsistentRead = false,
        $parallel = 10
    );

    /**
     * Gets table object for items in this repository.
     *
     * @return table
     *
     * @deprecated this interface might be removed any time in the future
     *
     * @internal only for advanced user, avoid using the table client directly whenever possible.
     */
    public function gettable();

    /**
     * Log Activity
     *
     * Logs the activity of a specific table and places that into another logging table
     *
     * @param mixed $dataObj
     * @param int $offset
     *
     * @return bool
     *
     * @throws \ReflectionException
     */
    public function logActivity($dataObj, int $offset = 0);

    /**
     * Gets table name.
     *
     * @return string
     */
    public function gettableName(): string;

    /**
     * Sets table name.
     *
     * @param string $tableName
     */
    public function settableName(string $tableName): void;
}
