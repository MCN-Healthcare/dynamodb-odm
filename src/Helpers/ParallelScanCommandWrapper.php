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

class ParallelScanCommandWrapper
{
    /**
     * @param DynamoDbClient $dbClient
     * @param                $tableName
     * @param callable       $callback
     * @param                $filterExpression
     * @param array          $fieldsMapping
     * @param array          $paramsMapping
     * @param                $indexName
     * @param                $evaluationLimit
     * @param                $isConsistentRead
     * @param                $isAscendingOrder
     * @param                $totalSegments
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
                      $evaluationLimit,
                      $isConsistentRead,
                      $isAscendingOrder,
                      $totalSegments,
                      $countOnly,
                      $projectedAttributes
    )
    {
        $ret               = 0;
        $stoppedByCallback = false;
        $lastKeys          = [];
        $finished          = 0;
        for ($i = 0; $i < $totalSegments; ++$i) {
            $lastKeys[$i] = null;
        }

        while (!$stoppedByCallback && $finished < $totalSegments) {
            $promises = [];
            foreach ($lastKeys as $i => $lastKey) {
                if ($finished == 0 || $lastKey) {
                    $asyncCommandWrapper = new ScanAsyncCommandWrapper();
                    $promise             = $asyncCommandWrapper(
                        $dbClient,
                        $tableName,
                        $filterExpression,
                        $fieldsMapping,
                        $paramsMapping,
                        $indexName,
                        $lastKeys[$i],
                        $evaluationLimit,
                        $isConsistentRead,
                        $isAscendingOrder,
                        $i,
                        $totalSegments,
                        $countOnly,
                        $projectedAttributes
                    );
                    $promise->then(
                        function (Result $result) use (
                            &$lastKeys,
                            $i,
                            &$ret,
                            &$finished,
                            $callback,
                            $countOnly,
                            &$stoppedByCallback
                        ) {
                            if ($stoppedByCallback) {
                                return;
                            }
                            $lastKeys[$i] = isset($result['LastEvaluatedKey']) ? $result['LastEvaluatedKey'] : null;
                            if ($lastKeys[$i] === null) {
                                $finished++;
                            }
                            if ($countOnly) {
                                $ret += $result['Count'];
                            }
                            else {
                                $items = isset($result['Items']) ? $result['Items'] : [];
                                //\mdebug("Total items = %d, seg = %d", count($items), $i);
                                foreach ($items as $typedItem) {
                                    $item = Item::createFromTypedArray($typedItem);
                                    if (false === call_user_func($callback, $item->toArray(), $i)) {
                                        $stoppedByCallback = true;
                                        break;
                                    }
                                }

                                $ret += count($items);
                            }
                        }
                    );
                    $promises[] = $promise;
                }
            }
            if ($promises) {
                \GuzzleHttp\Promise\all($promises)->wait();
            }
        }

        return $ret;
    }
}
