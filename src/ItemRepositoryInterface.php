<?php
namespace McnHealthcare\ODM\Dynamodb;

use McnHealthcare\ODM\Dynamodb\Helpers\Index;
use Doctrine\Common\Annotations\AnnotationException;
use ReflectionException;
use SplDoublyLinkedList;

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
    public function batchGet($groupOfKeys, bool $isConsistentRead = false): array;

    /**
     * Clear list of managed items.
     */
    public function clear(): void;

    /**
     * Unmanages an instance of an item.
     *
     * @param object $obj Item to detach.
     */
    public function detach(object $obj): void;

    /**
     * Flush managed items to db.
     *
     * @throws AnnotationException
     * @throws ReflectionException
     */
    public function flush(): void;

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
    ): void;

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
    ): int;

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
    ): void;

    /**
     * Makes item a managed item.
     *
     * @param $obj
     */
    public function persist($obj): void;

    /**
     * Persist for the Activity Logger
     *
     * @param $obj
     */
    public function persistLoggable($obj): void;

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
    ): array;

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
     * @return SplDoublyLinkedList
     */
    public function queryAll(
        $conditions = '',
        array $params = [],
        $indexName = Index::PRIMARY_INDEX,
        $filterExpression = '',
        $isConsistentRead = false,
        $isAscendingOrder = true
    ): SplDoublyLinkedList;

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
    ): void;

    /**
     * Query results count.
     *
     * @param mixed $conditions
     * @param array $params
     * @param bool $indexName
     * @param string $filterExpression
     * @param bool $isConsistentRead
     *
     * @return int
     */
    public function queryCount(
        $conditions,
        array $params,
        $indexName = Index::PRIMARY_INDEX,
        $filterExpression = '',
        $isConsistentRead = false
    ): int;

    /**
     * Reload entity from db.
     *
     * @param mixed $obj
     * @param bool $persistIfNotManaged
     */
    public function refresh($obj, $persistIfNotManaged = false): void;

    /**
     * Flags item to be removed ferom db on next flush.
     *
     * @param $obj
     */
    public function remove($obj): void;

    /**
     * Remove all items.
     *
     * @throws \Doctrine\Common\Annotations\AnnotationException
     * @throws \ReflectionException
     */
    public function removeAll(): void;

    /**
     * Remove item(s) by primary key.
     *
     * @param mixed $keys
     */
    public function removeById($keys): void;

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
    ): array;

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
    ): SplDoublyLinkedList;

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
    ): void;

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
    ): int;

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
    public function logActivity($dataObj, int $offset = 0): bool;

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

    /**
     * Gets a query builder for item.
     *
     * @return QueryInterface
     */
    public function getQueryBuilder(): QueryInterface;
}
