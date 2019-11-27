<?php
/*
 * This file is part AWS DynamoDB ODM.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace McnHealthcare\ODM\Dynamodb\Helpers;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\Result;

class MultiQueryCommandWrapper
{
    function __invoke(DynamoDbClient $dbClient,
                      $tableName,
                      callable $callback,
                      $hashKeyName,
                      $hashKeyValues,
                      $rangeKeyConditions,
                      array $fieldsMapping,
                      array $paramsMapping,
                      $indexName,
                      $filterExpression,
                      $evaluationLimit,
                      $isConsistentRead,
                      $isAscendingOrder,
                      $concurrency,
                      $projectedFields
    )
    {
        $fieldsMapping["#" . $hashKeyName] = $hashKeyName;
        $keyConditions                     = sprintf(
            "#%s = :%s",
            $hashKeyName,
            $hashKeyName
        );
        if ($rangeKeyConditions) {
            $keyConditions .= " AND " . $rangeKeyConditions;
        }
        $concurrency = min($concurrency, count($hashKeyValues));

        $queue = new \SplQueue();
        foreach ($hashKeyValues as $hashKeyValue) {
            $queue->push([$hashKeyValue, false]);
        }

        $stopped = false;

        $generator = function () use (
            &$stopped,
            $dbClient,
            $tableName,
            $callback,
            $queue,
            $hashKeyName,
            $keyConditions,
            $fieldsMapping,
            $paramsMapping,
            $indexName,
            $filterExpression,
            $evaluationLimit,
            $isConsistentRead,
            $isAscendingOrder,
            $projectedFields
        ) {
            while (!$stopped && !$queue->isEmpty()) {
                list($hashKeyValue, $lastKey) = $queue->shift();
                if ($lastKey === null) {
                    //minfo("Finished for hash key %s", $hashKeyValue);
                    continue;
                }
                $paramsMapping[":" . $hashKeyName] = $hashKeyValue;
                $asyncWrapper                      = new QueryAsyncCommandWrapper();
                $promise                           = $asyncWrapper(
                    $dbClient,
                    $tableName,
                    $keyConditions,
                    $fieldsMapping,
                    $paramsMapping,
                    $indexName,
                    $filterExpression,
                    $lastKey,
                    $evaluationLimit,
                    $isConsistentRead,
                    $isAscendingOrder,
                    false,
                    $projectedFields
                );
                yield $hashKeyValue => $promise;
            }
        };

        while (!$stopped && !$queue->isEmpty()) {
            /** @noinspection PhpUnusedParameterInspection */
            \GuzzleHttp\Promise\each_limit(
                $generator(),
                $concurrency,
                function (Result $result, $hashKeyValue) use ($callback, $queue, &$stopped) {
                    $lastKey = isset($result['LastEvaluatedKey']) ? $result['LastEvaluatedKey'] : null;
                    $items   = isset($result['Items']) ? $result['Items'] : [];
                    foreach ($items as $typedItem) {
                        $item = Item::createFromTypedArray($typedItem);
                        if (false === call_user_func($callback, $item->toArray())) {
                            $stopped = true;
                            break;
                        }
                    }
                    $queue->push([$hashKeyValue, $lastKey]);
                }
                ,
                function (DynamoDbException $reason,
                    /** @noinspection PhpUnusedParameterInspection */
                          $hashKeyValue) {
                    throw $reason;
                }
            )->wait();
        }
    }
}
