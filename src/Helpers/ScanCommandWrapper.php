<?php
/*
 * This file is part AWS DynamoDB ODM.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace McnHealthcare\ODM\Dynamodb\Helpers;

use Aws\DynamoDb\DynamoDbClient;
use Aws\Result;

class ScanCommandWrapper
{
    /**
     * @param DynamoDbClient $dbClient
     * @param                $tableName
     * @param callable       $callback
     * @param                $filterExpression
     * @param array          $fieldsMapping
     * @param array          $paramsMapping
     * @param                $indexName
     * @param                $lastKey
     * @param                $evaluationLimit
     * @param                $isConsistentRead
     * @param                $isAscendingOrder
     * @param                $countOnly
     * @param array          $projectedAttributes
     *
     * @return int
     */
    function __invoke(DynamoDbClient $dbClient,
                      $tableName,
                      callable $callback,
                      $filterExpression,
                      array $fieldsMapping,
                      array $paramsMapping,
                      $indexName,
                      &$lastKey,
                      $evaluationLimit,
                      $isConsistentRead,
                      $isAscendingOrder,
                      $countOnly,
                      $projectedAttributes
    )
    {
        $asyncCommandWrapper = new ScanAsyncCommandWrapper();
        $promise             = $asyncCommandWrapper(
            $dbClient,
            $tableName,
            $filterExpression,
            $fieldsMapping,
            $paramsMapping,
            $indexName,
            $lastKey,
            $evaluationLimit,
            $isConsistentRead,
            $isAscendingOrder,
            0,
            1,
            $countOnly,
            $projectedAttributes
        );
        $promise->then(
            function (Result $result) use (&$lastKey, &$ret, $callback, $countOnly) {
                $lastKey = isset($result['LastEvaluatedKey']) ? $result['LastEvaluatedKey'] : null;
                if ($countOnly) {
                    $ret = $result['Count'];
                }
                else {
                    $items = isset($result['Items']) ? $result['Items'] : [];
                    $ret   = 0;
                    foreach ($items as $typedItem) {
                        $ret++;
                        $item = Item::createFromTypedArray($typedItem);
                        if (false === call_user_func($callback, $item->toArray())) {
                            break;
                        }
                    }
                }
            }
        );
        $promise->wait();

        return $ret;
    }
}
