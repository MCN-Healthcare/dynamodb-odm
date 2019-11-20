<?php
/*
 * This file is part AWS DynamoDB ODM.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace McnHealthcare\ODM\Dynamodb;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use McnHealthcare\ODM\Dynamodb\Helpers\Index;
use McnHealthcare\ODM\Dynamodb\Helpers\Table;
use McnHealthcare\ODM\Dynamodb\Exceptions\DataConsistencyException;
use McnHealthcare\ODM\Dynamodb\Exceptions\ODMException;
use McnHealthcare\ODM\Dynamodb\Exceptions\UnderlyingDatabaseException;

/**
 * Class ItemRepository
 * Repository for odm entities/items.
 */
class ItemRepository implements ItemRepositoryInterface
{
    /**
     * @var ItemManager
     */
    protected $itemManager;

    /**
     * @var ItemReflection
     */
    protected $itemReflection;

    /**
     * @var ActivityLoggingDetails
     */
    private $loggingDetails;

    /**
     * @var Table
     */
    protected $table;

    /**
     * Maps object id to managed object
     *
     * @var ManagedItemState[]
     */
    protected $itemManaged = [];

    /**
     * @var string
     */
    private $loggedtable;

    /**
     * @var string
     */
    private $changedBy;

    /**
     * @var string
     */
    private $tableName;

    /**
     * @var string
     */
    private $loggabletable;

    /**
     * @var ItemManager
     */
    private $logItemManager;

    /**
     * @var ItemReflection
     */
    private $logItemReflection;

    /**
     * @var ManagedItemState[]
     */
    private $itemLogManaged = [];

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * ItemRepository constructor.
     *
     * @param ItemReflection $itemReflection Item metadata.
     * @param ItemManager $itemManager Item manager handling this repository.
     * @param ActivityLoggingDetails $loggingDetails
     * Who data for logging to dynamodb.
     * @param LoggerInterface $logger
     * For writing log entries.
     *
     * @throws \ReflectionException
     */
    public function __construct(
        ItemReflection $itemReflection,
        ItemManager $itemManager,
        ActivityLoggingDetails $loggingDetails,
        LoggerInterface $logger = null
    ) {
        $this->itemManager = $itemManager;
        $this->itemReflection = $itemReflection;
        $this->logger = $logger ?? new NullLogger();

        // initialize table
        $tableName = $itemManager->getDefaulttablePrefix() . $this->itemReflection->gettableName();
        $this->tableName = $tableName;

        $this->table = new Table(
            $itemManager->getDynamoDbClient(),
            $tableName,
            $this->itemReflection->getAttributeTypes(),
            $this->logger
        );

        // Activity Logging
        $activityLogging = new ActivityLogging(
            $this->itemReflection,
            $this->itemManager
        );

        $this->logItemManager = $activityLogging->getLogItemManager();
        $this->logItemReflection = $activityLogging->getLogItemReflection();

        $loggingDetails->setLoggedtable($tableName);
        $this->loggedtable = $loggingDetails->getLoggedtable();
        $this->changedBy = $loggingDetails->getChangedBy();
        $this->loggingDetails = $loggingDetails;
        $logtableName = $itemManager->getDefaulttablePrefix() . $loggingDetails->getLogtableName();

        $this->loggabletable = new Table(
            $itemManager->getDynamoDbClient(),
            $logtableName,
            $this->itemReflection->getAttributeTypes(),
            $this->logger
        );
    }

    /**
     * {@inheritdoc}
     */
    public function batchGet($groupOfKeys, $isConsistentRead = false)
    {
        /** @var string[] $fieldNameMapping */
        $fieldNameMapping = $this->itemReflection->getFieldNameMapping();
        $groupOfTranslatedKeys = [];
        foreach ($groupOfKeys as $keys) {
            $translatedKeys = [];
            foreach ($keys as $k => $v) {
                if ( ! isset($fieldNameMapping[$k])) {
                    throw new ODMException("Cannot find primary index field: $k!");
                }
                $k = $fieldNameMapping[$k];
                $translatedKeys[$k] = $v;
            }
            $groupOfTranslatedKeys[] = $translatedKeys;
        }
        $resultSet = $this->table->batchGet(
            $groupOfTranslatedKeys,
            $isConsistentRead,
            10,
            $this->itemReflection->getProjectedAttributes()
        );
        if (is_array($resultSet)) {
            $ret = [];
            foreach ($resultSet as $singleResult) {
                $obj = $this->persistFetchedItemData($singleResult);
                $ret[] = $obj;
            }

            return $ret;
        } else {
            throw new UnderlyingDatabaseException("Result returned from dynamodb for BatchGet() is not an array!");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->itemManaged = [];
    }

    /**
     * {@inheritdoc}
     */
    public function detach($obj)
    {
        if ( ! $this->itemReflection->getReflectionClass()->isInstance($obj)) {
            throw new ODMException(
                "Object detached is not of correct type, expected: " . $this->itemReflection->getItemClass()
            );
        }
        $id = $this->itemReflection->getPrimaryIdentifier($obj);
        if ( ! isset($this->itemManaged[$id])) {
            throw new ODMException("Object is not managed: " . print_r($obj, true));
        }

        unset($this->itemManaged[$id]);
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        $skipCAS = $this->itemManager->shouldSkipCheckAndSet()
            || (count($this->itemReflection->getCasProperties()) == 0);
        $removed = [];
        $batchRemovalKeys = [];
        $batchSetItems = [];
        $batchNewItemStates = new \SplStack();
        $batchUpdateItemStates = new \SplStack();

        // Iterate through each of the items/objects/records to process
        foreach ($this->itemManaged as $oid => $managedItemState) {
            $item = $managedItemState->getItem();

            /* */
            // Activity Log - Check if the activity on the entity should be logged, and if so, write it to the logging table!
            if ($this->itemManager->checkLoggable($this->itemReflection->getItemClass())) {
                $this->logActivity($item, $this->loggingDetails->getOffset());
            }

            // Delete
            if ($managedItemState->isRemoved()) {
                $batchRemovalKeys[] = $this->itemReflection->getPrimaryKeys($item);
                $removed[] = $oid;
            } // Create
            elseif ($managedItemState->isNew()) {
                if ($this->itemReflection->getItemDefinition()->projected) {
                    throw new ODMException(
                        \sprintf(
                            "Not possible to create a projected item of type %s, try create the full-featured item instead!",
                            $this->itemReflection->getItemClass()
                        )
                    );
                }

                $managedItemState->updateCASTimestamps();
                $managedItemState->updatePartitionedHashKeys();

                if ($skipCAS) {
                    $batchSetItems[] = $this->itemReflection->dehydrate($item);
                    $batchNewItemStates->push($managedItemState);
                } else {
                    $ret = $this->table->set(
                        $this->itemReflection->dehydrate($item),
                        $managedItemState->getCheckConditionData()
                    );
                    if ($ret === false) {
                        throw new DataConsistencyException(
                            "Item exists! type = " . $this->itemReflection->getItemClass()
                        );
                    }
                    $managedItemState->setState(ManagedItemState::STATE_MANAGED);
                    $managedItemState->setUpdated();
                }
            } // Update
            else {
                $hasData = $managedItemState->hasDirtyData();
                if ($hasData) {
                    if ($this->itemReflection->getItemDefinition()->projected) {
                        throw new ODMException(
                            \sprintf(
                                "Not possible to update a projected item of type %s, try updating the full-featured item instead!"
                                . " You could also detach the modified item to bypass this exception!",
                                $this->itemReflection->getItemClass()
                            )
                        );
                    }

                    $managedItemState->updateCASTimestamps();
                    $managedItemState->updatePartitionedHashKeys();
                    if ($skipCAS) {
                        $batchSetItems[] = $this->itemReflection->dehydrate($item);
                        $batchUpdateItemStates->push($managedItemState);
                    } else {

                        $ret = $this->table->set(
                            $this->itemReflection->dehydrate($item),
                            $managedItemState->getCheckConditionData()
                        );
                        if ( ! $ret) {
                            throw new DataConsistencyException(
                                "Item updated elsewhere! type = " . $this->itemReflection->getItemClass()
                            );
                        }
                        $managedItemState->setUpdated();
                    }
                }
            }
        }

        if ($batchRemovalKeys) {
            $this->table->batchDelete($batchRemovalKeys);
        }

        if ($batchSetItems) {
            $this->table->batchPut($batchSetItems);
        }

        /** @var ManagedItemState $managedItemState */
        // Batch Create
        foreach ($batchNewItemStates as $managedItemState) {
            $managedItemState->setState(ManagedItemState::STATE_MANAGED);
            $managedItemState->setUpdated();
        }

        // Batch Update
        foreach ($batchUpdateItemStates as $managedItemState) {
            $managedItemState->setState(ManagedItemState::STATE_MANAGED);
            $managedItemState->setUpdated();
        }

        // Delete
        foreach ($removed as $id) {
            unset($this->itemManaged[$id]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get($keys, $isConsistentRead = false)
    {
        /** @var string[] $fieldNameMapping */
        $fieldNameMapping = $this->itemReflection->getFieldNameMapping();
        $translatedKeys = [];
        foreach ($keys as $k => $v) {
            if ( ! isset($fieldNameMapping[$k])) {
                throw new ODMException("Cannot find primary index field: $k!");
            }
            $k = $fieldNameMapping[$k];
            $translatedKeys[$k] = $v;
        }

        // return existing item
        if ( ! $isConsistentRead) {
            $id = $this->itemReflection->getPrimaryIdentifier($translatedKeys);
            if (isset($this->itemManaged[$id])) {
                return $this->itemManaged[$id]->getItem();
            }
        }

        $result = $this->table->get(
            $translatedKeys,
            $isConsistentRead,
            $this->itemReflection->getProjectedAttributes()
        );

        if (is_array($result)) {
            $obj = $this->persistFetchedItemData($result);

            return $obj;
        } elseif ($result === null) {
            return null;
        } else {
            throw new UnderlyingDatabaseException("Result returned from dynamodb is not an array!");
        }
    }

    /**
     * {@inheritdoc}
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
    ) {
        if ( ! is_array($hashKeyValues)) {
            $hashKeyValues = [$hashKeyValues];
        }
        $partitionedHashKeyValues = [];
        foreach ($hashKeyValues as $hashKeyValue) {
            $partitionedHashKeyValues = array_merge(
                $partitionedHashKeyValues,
                $this->itemReflection->getAllPartitionedValues($hashKey, $hashKeyValue)
            );
        }
        $fields = array_merge($this->getFieldsArray($rangeConditions), $this->getFieldsArray($filterExpression));
        $this->table->multiQueryAndRun(
            function ($result) use ($callback) {
                $obj = $this->persistFetchedItemData($result);

                return call_user_func($callback, $obj);
            },
            $hashKey,
            $partitionedHashKeyValues,
            $rangeConditions,
            $fields,
            $params,
            $indexName,
            $filterExpression,
            $evaluationLimit,
            $isConsistentRead,
            $isAscendingOrder,
            $concurrency,
            $this->itemReflection->getProjectedAttributes()
        );
    }

    /**
     * {@inheritdoc}
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
    ) {
        if ( ! is_array($hashKeyValues)) {
            $hashKeyValues = [$hashKeyValues];
        }
        $partitionedHashKeyValues = [];
        foreach ($hashKeyValues as $hashKeyValue) {
            $partitionedHashKeyValues = array_merge(
                $partitionedHashKeyValues,
                $this->itemReflection->getAllPartitionedValues($hashKey, $hashKeyValue)
            );
        }
        $fields = array_merge($this->getFieldsArray($rangeConditions), $this->getFieldsArray($filterExpression));
        $count = 0;
        $this->table->multiQueryAndRun(
            function () use (&$count) {
                $count++;
            },
            $hashKey,
            $partitionedHashKeyValues,
            $rangeConditions,
            $fields,
            $params,
            $indexName,
            $filterExpression,
            10000,
            $isConsistentRead,
            true,
            $concurrency,
            $this->itemReflection->getProjectedAttributes()
        );

        return $count;
    }

    /**
     * {@inheritdoc}
     */
    public function parallelScanAndRun(
        $parallel,
        callable $callback,
        $conditions = '',
        array $params = [],
        $indexName = Index::PRIMARY_INDEX,
        $isConsistentRead = false,
        $isAscendingOrder = true
    ) {
        $fields = $this->getFieldsArray($conditions);
        $this->table->parallelScanAndRun(
            $parallel,
            function ($result) use ($callback) {
                $obj = $this->persistFetchedItemData($result);

                return call_user_func($callback, $obj);
            },
            $conditions,
            $fields,
            $params,
            $indexName,
            $isConsistentRead,
            $isAscendingOrder,
            $this->itemReflection->getProjectedAttributes()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function persist($obj)
    {
        if ( ! $this->itemReflection->getReflectionClass()->isInstance($obj)) {
            throw new ODMException(
                "Persisting wrong object, expecting: " .
                $this->itemReflection->getItemClass() .
                ", received: " .
                print_r($obj, true)
            );
        }
        $id = $this->itemReflection->getPrimaryIdentifier($obj);
        if (isset($this->itemManaged[$id])) {
            throw new ODMException("Persisting existing object: " . print_r($obj, true));
        }

        $managedState = new ManagedItemState($this->itemReflection, $obj);
        $managedState->setState(ManagedItemState::STATE_NEW);
        $this->itemManaged[$id] = $managedState;
    }

    /**
     * {@inheritdoc}
     */
    public function persistLoggable($obj)
    {
        if ( ! $this->logItemReflection->getReflectionClass()->isInstance($obj)) {
            throw new ODMException(
                "Persisting wrong object, expecting: " .
                $this->logItemReflection->getItemClass() .
                ", received: " .
                print_r($obj, true)
            );
        }
        $id = $this->logItemReflection->getPrimaryIdentifier($obj);
        if (isset($this->logItemManaged[$id])) {
            throw new ODMException("Persisting existing object: " . print_r($obj, true));
        }

        $managedLogState = new ManagedItemState($this->logItemReflection, $obj);
        $managedLogState->setState(ManagedItemState::STATE_NEW);
        $this->itemLogManaged[$id] = $managedLogState;
    }

    /**
     * {@inheritdoc}
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
    ) {
        $fields = array_merge($this->getFieldsArray($conditions), $this->getFieldsArray($filterExpression));
        $results = $this->table->query(
            $conditions,
            $fields,
            $params,
            $indexName,
            $filterExpression,
            $lastKey,
            $evaluationLimit,
            $isConsistentRead,
            $isAscendingOrder,
            $this->itemReflection->getProjectedAttributes()
        );
        $ret = [];
        foreach ($results as $result) {
            $obj = $this->persistFetchedItemData($result);
            $ret[] = $obj;
        }

        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    public function queryAll(
        $conditions = '',
        array $params = [],
        $indexName = Index::PRIMARY_INDEX,
        $filterExpression = '',
        $isConsistentRead = false,
        $isAscendingOrder = true
    ) {
        $ret = new \SplDoublyLinkedList();
        $this->queryAndRun(
            function ($item) use ($ret) {
                $ret->push($item);
            },
            $conditions,
            $params,
            $indexName,
            $filterExpression,
            $isConsistentRead,
            $isAscendingOrder
        );

        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    public function queryAndRun(
        callable $callback,
        $conditions = '',
        array $params = [],
        $indexName = Index::PRIMARY_INDEX,
        $filterExpression = '',
        $isConsistentRead = false,
        $isAscendingOrder = true
    ) {
        $fields = array_merge($this->getFieldsArray($conditions), $this->getFieldsArray($filterExpression));
        $this->table->queryAndRun(
            function ($result) use ($callback) {
                $obj = $this->persistFetchedItemData($result);

                return call_user_func($callback, $obj);
            },
            $conditions,
            $fields,
            $params,
            $indexName,
            $filterExpression,
            $isConsistentRead,
            $isAscendingOrder,
            $this->itemReflection->getProjectedAttributes()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function queryCount(
        $conditions,
        array $params,
        $indexName = Index::PRIMARY_INDEX,
        $filterExpression = '',
        $isConsistentRead = false
    ) {
        $fields = array_merge($this->getFieldsArray($conditions), $this->getFieldsArray($filterExpression));

        return $this->table->queryCount(
            $conditions,
            $fields,
            $params,
            $indexName,
            $filterExpression,
            $isConsistentRead
        );
    }

    /**
     * {@inheritdoc}
     */
    public function refresh($obj, $persistIfNotManaged = false)
    {
        if (! $this->itemReflection->getReflectionClass()->isInstance($obj)) {
            throw new ODMException(
                "Object refreshed is not of correct type, expected: " . $this->itemReflection->getItemClass()
            );
        }

        $id = $this->itemReflection->getPrimaryIdentifier($obj);
        if (! isset($this->itemManaged[$id])) {
            if (! $persistIfNotManaged) {
                throw new ODMException("Object is not managed: " . print_r($obj, true));
            }
            $this->itemManaged[$id] = new ManagedItemState($this->itemReflection, $obj);
        }

        $objRefreshed = $this->get($this->itemReflection->getPrimaryKeys($obj, false), true);

        if (! $objRefreshed && $persistIfNotManaged) {
            $this->itemManaged[$id]->setState(ManagedItemState::STATE_NEW);
        }

    }

    /**
     * {@inheritdoc}
     */
    public function remove($obj)
    {
        if (! $this->itemReflection->getReflectionClass()->isInstance($obj)) {
            throw new ODMException(
                "Object removed is not of correct type, expected: " . $this->itemReflection->getItemClass()
            );
        }
        $id = $this->itemReflection->getPrimaryIdentifier($obj);
        if (! isset($this->itemManaged[$id])) {
            throw new ODMException("Object is not managed: " . print_r($obj, true));
        }

        $this->itemManaged[$id]->setState(ManagedItemState::STATE_REMOVED);
    }

    /**
     * {@inheritdoc}
     */
    public function removeAll()
    {
        do {
            $this->clear();
            $this->scanAndRun(
                function ($item) {
                    $this->remove($item);
                    if (count($this->itemManaged) > 1000) {
                        return false;
                    }

                    return true;
                },
                '',
                [],
                Index::PRIMARY_INDEX,
                true,
                true,
                10
            );
            if (count($this->itemManaged) == 0) {
                break;
            }
            $skipCAS = $this->itemManager->shouldSkipCheckAndSet();
            $this->itemManager->setSkipCheckAndSet(true);
            $this->flush();
            $this->itemManager->setSkipCheckAndSet($skipCAS);
        } while (true);

    }

    /**
     * {@inheritdoc}
     */
    public function removeById($keys)
    {
        $obj = $this->get($keys, true);
        if ($obj) {
            $this->remove($obj);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function scan(
        $conditions = '',
        array $params = [],
        $indexName = Index::PRIMARY_INDEX,
        &$lastKey = null,
        $evaluationLimit = 30,
        $isConsistentRead = false,
        $isAscendingOrder = true
    ) {
        $fields = $this->getFieldsArray($conditions);
        $results = $this->table->scan(
            $conditions,
            $fields,
            $params,
            $indexName,
            $lastKey,
            $evaluationLimit,
            $isConsistentRead,
            $isAscendingOrder,
            $this->itemReflection->getProjectedAttributes()
        );
        $ret = [];
        foreach ($results as $result) {
            $obj = $this->persistFetchedItemData($result);
            $ret[] = $obj;
        }

        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    public function scanAll(
        $conditions = '',
        array $params = [],
        $indexName = Index::PRIMARY_INDEX,
        $isConsistentRead = false,
        $isAscendingOrder = true,
        $parallel = 1
    ) {
        $ret = new \SplDoublyLinkedList();
        $this->scanAndRun(
            function ($item) use ($ret) {
                $ret->push($item);
            },
            $conditions,
            $params,
            $indexName,
            $isConsistentRead,
            $isAscendingOrder,
            $parallel
        );

        return $ret;
    }

    /**
     * {@inheritdoc}
     */
    public function scanAndRun(
        callable $callback,
        $conditions = '',
        array $params = [],
        $indexName = Index::PRIMARY_INDEX,
        $isConsistentRead = false,
        $isAscendingOrder = true,
        $parallel = 1
    ) {
        $resultCallback = function ($result) use ($callback) {
            $obj = $this->persistFetchedItemData($result);

            return call_user_func($callback, $obj);
        };

        $fields = $this->getFieldsArray($conditions);

        if ($parallel > 1) {
            $this->table->parallelScanAndRun(
                $parallel,
                $resultCallback,
                $conditions,
                $fields,
                $params,
                $indexName,
                $isConsistentRead,
                $isAscendingOrder,
                $this->itemReflection->getProjectedAttributes()
            );
        } elseif ($parallel == 1) {
            $this->table->scanAndRun(
                $resultCallback,
                $conditions,
                $fields,
                $params,
                $indexName,
                $isConsistentRead,
                $isAscendingOrder,
                $this->itemReflection->getProjectedAttributes()
            );
        } else {
            throw new \InvalidArgumentException("Parallel can only be an integer greater than 0");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function scanCount(
        $conditions = '',
        array $params = [],
        $indexName = Index::PRIMARY_INDEX,
        $isConsistentRead = false,
        $parallel = 10
    ) {
        $fields = $this->getFieldsArray($conditions);

        return $this->table->scanCount(
            $conditions,
            $fields,
            $params,
            $indexName,
            $isConsistentRead,
            $parallel
        );
    }

    /**
     * {@inheritdoc}
     */
    public function gettable()
    {
        return $this->table;
    }


    /**
     * {@inheritdoc}
     */
    public function logActivity($dataObj, int $offset = 0)
    {
        $log = new ActivityLogging(
            $this->itemReflection, $this->itemManager, $this->changedBy, $this->loggedtable, $offset
        );

        return $log->insertIntoActivityLog($dataObj);

    }

    /**
     * @param $conditions
     *
     * @return array
     */
    protected function getFieldsArray($conditions)
    {
        $ret = preg_match_all('/#(?P<field>[a-zA-Z_][a-zA-Z0-9_]*)/', $conditions, $matches);
        if ( ! $ret) {
            return [];
        }

        $result = [];
        $fieldNameMapping = $this->itemReflection->getFieldNameMapping();
        if (isset($matches['field']) && is_array($matches['field'])) {
            foreach ($matches['field'] as $fieldName) {
                if ( ! isset($fieldNameMapping[$fieldName])) {
                    throw new ODMException("Cannot find field named $fieldName!");
                }
                $result["#" . $fieldName] = $fieldNameMapping[$fieldName];
            }
        }

        return $result;
    }

    /**
     * @param array $resultData
     *
     * @return mixed|object|null
     */
    protected function persistFetchedItemData(array $resultData)
    {
        $id = $this->itemReflection->getPrimaryIdentifier($resultData);
        if (isset($this->itemManaged[$id])) {
            if ($this->itemManaged[$id]->isNew()) {
                throw new ODMException("Conflict! Fetched remote data is also persisted. " . json_encode($resultData));
            }
            if ($this->itemManaged[$id]->isRemoved()) {
                throw new ODMException("Conflict! Fetched remote data is also removed. " . json_encode($resultData));
            }

            $obj = $this->itemManaged[$id]->getItem();
            $this->itemReflection->hydrate($resultData, $obj);
            $this->itemManaged[$id]->setOriginalData($resultData);
        } else {
            $obj = $this->itemReflection->hydrate($resultData);
            $this->itemManaged[$id] = new ManagedItemState($this->itemReflection, $obj, $resultData);
        }

        return $obj;
    }

    /**
     * {@inheritdoc}
     */
    public function gettableName(): string
    {
        return $this->tableName;
    }

    /**
     * {@inheritdoc}
     */
    public function settableName(string $tableName): void
    {
        $this->tableName = $tableName;
    }

    /**
     * {@inheritdoc}
     */
    public function getQueryBuilder(): QueryInterface
    {
        return clone $this->query;
    }
}
