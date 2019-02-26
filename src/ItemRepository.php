<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-06
 * Time: 12:17
 */

namespace Oasis\Mlib\ODM\Dynamodb;

use Oasis\Mlib\AwsWrappers\DynamoDbIndex;
use Oasis\Mlib\AwsWrappers\DynamoDbTable;
use Oasis\Mlib\ODM\Dynamodb\Exceptions\DataConsistencyException;
use Oasis\Mlib\ODM\Dynamodb\Exceptions\ODMException;
use Oasis\Mlib\ODM\Dynamodb\Exceptions\UnderlyingDatabaseException;

class ItemRepository
{
    /** @var  ItemManager */
    protected $itemManager;
    /** @var ItemReflection */
    protected $itemReflection;
    /** @var  DynamoDbTable */
    protected $dynamodbTable;
    
    /**
     * @var ManagedItemState[]
     * Maps object id to managed object
     */
    protected $itemManaged = [];
    /**
     * @var string
     */
    private $logTable;

    /**
     * ItemRepository constructor.
     * @param ItemReflection $itemReflection
     * @param ItemManager $itemManager
     * @param string $logTable
     */
    public function __construct(ItemReflection $itemReflection, ItemManager $itemManager, string $logTable = "activityLog")
    {
        $this->itemManager    = $itemManager;
        $this->itemReflection = $itemReflection;
        
        // initialize table
        $tableName           = $itemManager->getDefaultTablePrefix() . $this->itemReflection->getTableName();
        $this->dynamodbTable = new DynamoDbTable(
            $itemManager->getDynamodbConfig(),
            $tableName,
            $this->itemReflection->getAttributeTypes()
        );
        $this->logTable = $logTable;
    }

    /**
     * @param $groupOfKeys
     * @param bool $isConsistentRead
     * @return array
     */
    public function batchGet($groupOfKeys, $isConsistentRead = false)
    {
        /** @var string[] $fieldNameMapping */
        $fieldNameMapping      = $this->itemReflection->getFieldNameMapping();
        $groupOfTranslatedKeys = [];
        foreach ($groupOfKeys as $keys) {
            $translatedKeys = [];
            foreach ($keys as $k => $v) {
                if (!isset($fieldNameMapping[$k])) {
                    throw new ODMException("Cannot find primary index field: $k!");
                }
                $k                  = $fieldNameMapping[$k];
                $translatedKeys[$k] = $v;
            }
            $groupOfTranslatedKeys[] = $translatedKeys;
        }
        $resultSet = $this->dynamodbTable->batchGet(
            $groupOfTranslatedKeys,
            $isConsistentRead,
            10,
            $this->itemReflection->getProjectedAttributes()
        );
        if (is_array($resultSet)) {
            $ret = [];
            foreach ($resultSet as $singleResult) {
                $obj   = $this->persistFetchedItemData($singleResult);
                $ret[] = $obj;
            }
            
            return $ret;
        }
        else {
            throw new UnderlyingDatabaseException("Result returned from dynamodb for BatchGet() is not an array!");
        }
    }

    /**
     *
     */
    public function clear()
    {
        $this->itemManaged = [];
    }

    /**
     * @param $obj
     */
    public function detach($obj)
    {
        if (!$this->itemReflection->getReflectionClass()->isInstance($obj)) {
            throw new ODMException(
                "Object detached is not of correct type, expected: " . $this->itemReflection->getItemClass()
            );
        }
        $id = $this->itemReflection->getPrimaryIdentifier($obj);
        if (!isset($this->itemManaged[$id])) {
            throw new ODMException("Object is not managed: " . print_r($obj, true));
        }
        
        unset($this->itemManaged[$id]);
    }

    /**
     * @throws \Doctrine\Common\Annotations\AnnotationException
     * @throws \ReflectionException
     */
    public function flush()
    {
        $skipCAS               = $this->itemManager->shouldSkipCheckAndSet()
                                 || (count($this->itemReflection->getCasProperties()) == 0);
        $removed               = [];
        $batchRemovalKeys      = [];
        $batchSetItems         = [];
        $batchNewItemStates    = new \SplStack();
        $batchUpdateItemStates = new \SplStack();

        // Iterate through each of the items/objects/records to process
        foreach ($this->itemManaged as $oid => $managedItemState) {
            $item = $managedItemState->getItem();

            // Activity Log - Check if the activity on the entity should be logged, and if so, write it to the logging table!
            if ($this->itemManager->checkLoggable($this->itemReflection->getItemClass())) {
                $this->logActivity($item, $this->logTable);
            }

            // Delete
            if ($managedItemState->isRemoved()) {
                $batchRemovalKeys[] = $this->itemReflection->getPrimaryKeys($item);
                $removed[]          = $oid;
            }
            // Create
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
                }
                else {
                    $ret = $this->dynamodbTable->set(
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
            }
            // Update
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
                    }
                    else {
                        
                        $ret = $this->dynamodbTable->set(
                            $this->itemReflection->dehydrate($item),
                            $managedItemState->getCheckConditionData()
                        );
                        if (!$ret) {
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
            $this->dynamodbTable->batchDelete($batchRemovalKeys);
        }
        if ($batchSetItems) {
            $this->dynamodbTable->batchPut($batchSetItems);
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
     * @param $keys
     * @param bool $isConsistentRead
     * @return mixed|object|null
     */
    public function get($keys, $isConsistentRead = false)
    {
        /** @var string[] $fieldNameMapping */
        $fieldNameMapping = $this->itemReflection->getFieldNameMapping();
        $translatedKeys   = [];
        foreach ($keys as $k => $v) {
            if (!isset($fieldNameMapping[$k])) {
                throw new ODMException("Cannot find primary index field: $k!");
            }
            $k                  = $fieldNameMapping[$k];
            $translatedKeys[$k] = $v;
        }
        
        // return existing item
        if (!$isConsistentRead) {
            $id = $this->itemReflection->getPrimaryIdentifier($translatedKeys);
            if (isset($this->itemManaged[$id])) {
                return $this->itemManaged[$id]->getItem();
            }
        }
        
        $result = $this->dynamodbTable->get(
            $translatedKeys,
            $isConsistentRead,
            $this->itemReflection->getProjectedAttributes()
        );
        if (is_array($result)) {
            $obj = $this->persistFetchedItemData($result);
            
            return $obj;
        }
        elseif ($result === null) {
            return null;
        }
        else {
            throw new UnderlyingDatabaseException("Result returned from dynamodb is not an array!");
        }
    }

    /**
     * @param callable $callback
     * @param $hashKey
     * @param $hashKeyValues
     * @param $rangeConditions
     * @param array $params
     * @param $indexName
     * @param string $filterExpression
     * @param int $evaluationLimit
     * @param bool $isConsistentRead
     * @param bool $isAscendingOrder
     * @param int $concurrency
     */
    public function multiQueryAndRun(callable $callback,
                                     $hashKey,
                                     $hashKeyValues,
                                     $rangeConditions,
                                     array $params,
                                     $indexName,
                                     $filterExpression = '',
                                     $evaluationLimit = 30,
                                     $isConsistentRead = false,
                                     $isAscendingOrder = true,
                                     $concurrency = 10)
    {
        if (!is_array($hashKeyValues)) {
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
        $this->dynamodbTable->multiQueryAndRun(
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
     * @param $hashKey
     * @param $hashKeyValues
     * @param $rangeConditions
     * @param array $params
     * @param $indexName
     * @param string $filterExpression
     * @param bool $isConsistentRead
     * @param int $concurrency
     * @return int
     */
    public function multiQueryCount($hashKey,
                                    $hashKeyValues,
                                    $rangeConditions,
                                    array $params,
                                    $indexName,
                                    $filterExpression = '',
                                    $isConsistentRead = false,
                                    $concurrency = 10)
    {
        if (!is_array($hashKeyValues)) {
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
        $count  = 0;
        $this->dynamodbTable->multiQueryAndRun(
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
     * @param $parallel
     * @param callable $callback
     * @param string $conditions
     * @param array $params
     * @param bool $indexName
     * @param bool $isConsistentRead
     * @param bool $isAscendingOrder
     */
    public function parallelScanAndRun($parallel,
                                       callable $callback,
                                       $conditions = '',
                                       array $params = [],
                                       $indexName = DynamoDbIndex::PRIMARY_INDEX,
                                       $isConsistentRead = false,
                                       $isAscendingOrder = true
    )
    {
        $fields = $this->getFieldsArray($conditions);
        $this->dynamodbTable->parallelScanAndRun(
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
     * @param $obj
     */
    public function persist($obj)
    {
        if (!$this->itemReflection->getReflectionClass()->isInstance($obj)) {
            throw new ODMException("Persisting wrong object, expecting: " . $this->itemReflection->getItemClass());
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
     * @param $conditions
     * @param array $params
     * @param bool $indexName
     * @param string $filterExpression
     * @param null $lastKey
     * @param int $evaluationLimit
     * @param bool $isConsistentRead
     * @param bool $isAscendingOrder
     * @return array
     */
    public function query($conditions,
                          array $params,
                          $indexName = DynamoDbIndex::PRIMARY_INDEX,
                          $filterExpression = '',
                          &$lastKey = null,
                          $evaluationLimit = 30,
                          $isConsistentRead = false,
                          $isAscendingOrder = true)
    {
        $fields  = array_merge($this->getFieldsArray($conditions), $this->getFieldsArray($filterExpression));
        $results = $this->dynamodbTable->query(
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
        $ret     = [];
        foreach ($results as $result) {
            $obj   = $this->persistFetchedItemData($result);
            $ret[] = $obj;
        }
        
        return $ret;
    }
    
    /**
     * @param string $conditions
     * @param array  $params
     * @param bool   $indexName
     * @param string $filterExpression
     * @param bool   $isConsistentRead
     * @param bool   $isAscendingOrder
     *
     * @return \SplDoublyLinkedList
     */
    public function queryAll($conditions = '',
                             array $params = [],
                             $indexName = DynamoDbIndex::PRIMARY_INDEX,
                             $filterExpression = '',
                             $isConsistentRead = false,
                             $isAscendingOrder = true)
    {
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
     * @param callable $callback
     * @param string $conditions
     * @param array $params
     * @param bool $indexName
     * @param string $filterExpression
     * @param bool $isConsistentRead
     * @param bool $isAscendingOrder
     */
    public function queryAndRun(callable $callback,
                                $conditions = '',
                                array $params = [],
                                $indexName = DynamoDbIndex::PRIMARY_INDEX,
                                $filterExpression = '',
                                $isConsistentRead = false,
                                $isAscendingOrder = true)
    {
        $fields = array_merge($this->getFieldsArray($conditions), $this->getFieldsArray($filterExpression));
        $this->dynamodbTable->queryAndRun(
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
     * @param $conditions
     * @param array $params
     * @param bool $indexName
     * @param string $filterExpression
     * @param bool $isConsistentRead
     * @return array|bool|int
     */
    public function queryCount($conditions,
                               array $params,
                               $indexName = DynamoDbIndex::PRIMARY_INDEX,
                               $filterExpression = '',
                               $isConsistentRead = false)
    {
        $fields = array_merge($this->getFieldsArray($conditions), $this->getFieldsArray($filterExpression));
        
        return $this->dynamodbTable->queryCount(
            $conditions,
            $fields,
            $params,
            $indexName,
            $filterExpression,
            $isConsistentRead
        );
    }

    /**
     * @param $obj
     * @param bool $persistIfNotManaged
     */
    public function refresh($obj, $persistIfNotManaged = false)
    {
        if (!$this->itemReflection->getReflectionClass()->isInstance($obj)) {
            throw new ODMException(
                "Object refreshed is not of correct type, expected: " . $this->itemReflection->getItemClass()
            );
        }
        
        // 2017-03-24: we can refresh something that's not managed
        //$id = $this->itemReflection->getPrimaryIdentifier($obj);
        //if (!isset($this->itemManaged[$id])) {
        //    throw new ODMException("Object is not managed: " . print_r($obj, true));
        //}
        // end of change 2017-03-24
        
        $id = $this->itemReflection->getPrimaryIdentifier($obj);
        if (!isset($this->itemManaged[$id])) {
            if ($persistIfNotManaged) {
                $this->itemManaged[$id] = new ManagedItemState($this->itemReflection, $obj);
            }
            else {
                throw new ODMException("Object is not managed: " . print_r($obj, true));
            }
        }
        
        $objRefreshed = $this->get($this->itemReflection->getPrimaryKeys($obj, false), true);
        
        if (!$objRefreshed && $persistIfNotManaged) {
            $this->itemManaged[$id]->setState(ManagedItemState::STATE_NEW);
        }
        
    }

    /**
     * @param $obj
     */
    public function remove($obj)
    {
        if (!$this->itemReflection->getReflectionClass()->isInstance($obj)) {
            throw new ODMException(
                "Object removed is not of correct type, expected: " . $this->itemReflection->getItemClass()
            );
        }
        $id = $this->itemReflection->getPrimaryIdentifier($obj);
        if (!isset($this->itemManaged[$id])) {
            throw new ODMException("Object is not managed: " . print_r($obj, true));
        }
        
        $this->itemManaged[$id]->setState(ManagedItemState::STATE_REMOVED);
    }

    /**
     * @throws \Doctrine\Common\Annotations\AnnotationException
     * @throws \ReflectionException
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
                DynamoDbIndex::PRIMARY_INDEX,
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
     * @param $keys
     */
    public function removeById($keys)
    {
        $obj = $this->get($keys, true);
        if ($obj) {
            $this->remove($obj);
        }
    }

    /**
     * @param string $conditions
     * @param array $params
     * @param bool $indexName
     * @param null $lastKey
     * @param int $evaluationLimit
     * @param bool $isConsistentRead
     * @param bool $isAscendingOrder
     * @return array
     */
    public function scan($conditions = '',
                         array $params = [],
                         $indexName = DynamoDbIndex::PRIMARY_INDEX,
                         &$lastKey = null,
                         $evaluationLimit = 30,
                         $isConsistentRead = false,
                         $isAscendingOrder = true)
    {
        $fields  = $this->getFieldsArray($conditions);
        $results = $this->dynamodbTable->scan(
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
        $ret     = [];
        foreach ($results as $result) {
            $obj   = $this->persistFetchedItemData($result);
            $ret[] = $obj;
        }
        
        return $ret;
    }
    
    /**
     * @param string $conditions
     * @param array  $params
     * @param bool   $indexName
     * @param bool   $isConsistentRead
     * @param bool   $isAscendingOrder
     * @param int    $parallel
     *
     * @return \SplDoublyLinkedList
     */
    public function scanAll($conditions = '',
                            array $params = [],
                            $indexName = DynamoDbIndex::PRIMARY_INDEX,
                            $isConsistentRead = false,
                            $isAscendingOrder = true,
                            $parallel = 1)
    {
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
     * @param callable $callback
     * @param string $conditions
     * @param array $params
     * @param bool $indexName
     * @param bool $isConsistentRead
     * @param bool $isAscendingOrder
     * @param int $parallel
     */
    public function scanAndRun(callable $callback,
                               $conditions = '',
                               array $params = [],
                               $indexName = DynamoDbIndex::PRIMARY_INDEX,
                               $isConsistentRead = false,
                               $isAscendingOrder = true,
                               $parallel = 1
    )
    {
        $resultCallback = function ($result) use ($callback) {
            $obj = $this->persistFetchedItemData($result);
            
            return call_user_func($callback, $obj);
        };
        
        $fields = $this->getFieldsArray($conditions);
        
        if ($parallel > 1) {
            $this->dynamodbTable->parallelScanAndRun(
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
        }
        elseif ($parallel == 1) {
            $this->dynamodbTable->scanAndRun(
                $resultCallback,
                $conditions,
                $fields,
                $params,
                $indexName,
                $isConsistentRead,
                $isAscendingOrder,
                $this->itemReflection->getProjectedAttributes()
            );
        }
        else {
            throw new \InvalidArgumentException("Parallel can only be an integer greater than 0");
        }
    }

    /**
     * @param string $conditions
     * @param array $params
     * @param bool $indexName
     * @param bool $isConsistentRead
     * @param int $parallel
     * @return int
     */
    public function scanCount($conditions = '',
                              array $params = [],
                              $indexName = DynamoDbIndex::PRIMARY_INDEX,
                              $isConsistentRead = false,
                              $parallel = 10)
    {
        $fields = $this->getFieldsArray($conditions);
        
        return $this->dynamodbTable->scanCount(
            $conditions,
            $fields,
            $params,
            $indexName,
            $isConsistentRead,
            $parallel
        );
    }
    
    /**
     * @internal    only for advanced user, avoid using the table client directly whenever possible.
     * @deprecated  this interface might be removed any time in the future
     *
     * @return DynamoDbTable
     */
    public function getDynamodbTable()
    {
        return $this->dynamodbTable;
    }


    /**
     * Log Activity
     *
     * Logs the activity of a specific table and places that into another logging table
     *
     * @param $dataObj
     * @param $logTable
     * @throws \Doctrine\Common\Annotations\AnnotationException
     * @throws \ReflectionException
     */
    public function logActivity($dataObj, string $logTable)
    {
        $log = new ActivityLogging($this->itemReflection, $this->itemManager , $logTable);

        $log->insertIntoActivityLog($dataObj, $logTable);

    }

    /**
     * @param $conditions
     * @return array
     */
    protected function getFieldsArray($conditions)
    {
        $ret = preg_match_all('/#(?P<field>[a-zA-Z_][a-zA-Z0-9_]*)/', $conditions, $matches);
        if (!$ret) {
            return [];
        }
        
        $result           = [];
        $fieldNameMapping = $this->itemReflection->getFieldNameMapping();
        if (isset($matches['field']) && is_array($matches['field'])) {
            foreach ($matches['field'] as $fieldName) {
                if (!isset($fieldNameMapping[$fieldName])) {
                    throw new ODMException("Cannot find field named $fieldName!");
                }
                $result["#" . $fieldName] = $fieldNameMapping[$fieldName];
            }
        }
        
        return $result;
    }

    /**
     * @param array $resultData
     * @return mixed|object|null
     */
    protected function persistFetchedItemData(array $resultData)
    {
        $id = $this->itemReflection->getPrimaryIdentifier($resultData);
        if (isset($this->itemManaged[$id])) {
            if ($this->itemManaged[$id]->isNew()) {
                throw new ODMException("Conflict! Fetched remote data is also persisted. " . json_encode($resultData));
            }
            elseif ($this->itemManaged[$id]->isRemoved()) {
                throw new ODMException("Conflict! Fetched remote data is also removed. " . json_encode($resultData));
            }
            
            $obj = $this->itemManaged[$id]->getItem();
            $this->itemReflection->hydrate($resultData, $obj);
            $this->itemManaged[$id]->setOriginalData($resultData);
        }
        else {
            $obj                    = $this->itemReflection->hydrate($resultData);
            $this->itemManaged[$id] = new ManagedItemState($this->itemReflection, $obj, $resultData);
        }
        
        return $obj;
    }
}
