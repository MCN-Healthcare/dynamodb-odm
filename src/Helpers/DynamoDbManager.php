<?php
/*
 * This file is part AWS DynamoDB ODM.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace McnHealthcare\ODM\Dynamodb\Helpers;

use Aws\DynamoDb\DynamoDbClient;
use Aws\AwsClientInterface;
use Aws\Result;
use Psr\Log\loggerInterface;
use Psr\Log\Nulllogger;

/**
 * Class DynamoDbManager
 * High level dynamodb management functions.
 */
class DynamoDbManager
{
    /**
     * @var array
     */
    protected $config;
    /**
     * @var DynamoDbClient
     */
    protected $db;
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Initialize instance.
     *
     * @param AwsClientInterface $client Dynamodb client.
     * @param LoggerInterface $logger Forwriting log entries.
     */
    public function __construct(
        AwsClientInterface $client,
        LoggerInterface $logger = null
    ) {
        $this->db = $client;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Gets list of dynamodb tables the client can access.
     *
     * @param string $pattern a pattern that table name should match,
     * if emtpy, all tables will be returned
     *
     * @return array
     */
    public function listTables($pattern = '/.*/')
    {
        $tables                 = [];
        $lastEvaluatedTableName = null;
        do {
            $args = [
                "Limit" => 30,
            ];
            if ($lastEvaluatedTableName) {
                $args['ExclusiveStartTableName'] = $lastEvaluatedTableName;
            }
            $cmd    = $this->db->getCommand(
                'ListTables',
                $args
            );
            $result = $this->db->execute($cmd);
            if (isset($result['LastEvaluatedTableName'])) {
                $lastEvaluatedTableName = $result['LastEvaluatedTableName'];
            }
            else {
                $lastEvaluatedTableName = null;
            }

            foreach ($result['TableNames'] as $tableName) {
                if (preg_match($pattern, $tableName)) {
                    $tables[] = $tableName;
                }
            }
        } while ($lastEvaluatedTableName != null);

        return $tables;
    }

    /**
     * @param                 $tableName
     * @param Index   $primaryIndex
     * @param Index[] $localSecondaryIndices
     * @param Index[] $globalSecondaryIndices
     * @param int             $readCapacity
     * @param int             $writeCapacity
     *
     * @return bool
     *
     * @internal param Index $primaryKey
     */
    public function createTable(
        $tableName,
        Index $primaryIndex,
        array $localSecondaryIndices = [],
        array $globalSecondaryIndices = [],
        $readCapacity = 5,
        $writeCapacity = 5
    ) {
        $attrDef = $primaryIndex->getAttributeDefinitions();
        foreach ($globalSecondaryIndices as $gsi) {
            $gsiDef  = $gsi->getAttributeDefinitions();
            $attrDef = array_merge($attrDef, $gsiDef);
        }
        foreach ($localSecondaryIndices as $lsi) {
            $lsiDef  = $lsi->getAttributeDefinitions();
            $attrDef = array_merge($attrDef, $lsiDef);
        }

        $attrDef = array_values($attrDef);

        $keySchema = $primaryIndex->getKeySchema();

        $gsiDef = [];
        foreach ($globalSecondaryIndices as $globalSecondaryIndex) {
            $gsiDef[] = [
                "IndexName"             => $globalSecondaryIndex->getName(),
                "KeySchema"             => $globalSecondaryIndex->getKeySchema(),
                "Projection"            => $globalSecondaryIndex->getProjection(),
                "ProvisionedThroughput" => [
                    "ReadCapacityUnits"  => $readCapacity,
                    "WriteCapacityUnits" => $writeCapacity,
                ],
            ];
        }

        $lsiDef = [];
        foreach ($localSecondaryIndices as $localSecondaryIndex) {
            $lsiDef[] = [
                "IndexName"  => $localSecondaryIndex->getName(),
                "KeySchema"  => $localSecondaryIndex->getKeySchema(),
                "Projection" => $localSecondaryIndex->getProjection(),
            ];
        }

        $args = [
            "TableName"             => $tableName,
            "ProvisionedThroughput" => [
                "ReadCapacityUnits"  => $readCapacity,
                "WriteCapacityUnits" => $writeCapacity,
            ],
            "AttributeDefinitions"  => $attrDef,
            "KeySchema"             => $keySchema,
        ];
        if ($gsiDef) {
            $args["GlobalSecondaryIndexes"] = $gsiDef;
        }
        if ($lsiDef) {
            $args["LocalSecondaryIndexes"] = $lsiDef;
        }

        $result = $this->db->createTable($args);

        if (isset($result['TableDescription']) && $result['TableDescription']) {
            return true;
        }
        else {
            return false;
        }
    }

    public function deleteTable($tableName)
    {
        $args   = [
            'TableName' => $tableName,
        ];
        $result = $this->db->deleteTable($args);

        if (isset($result['TableDescription']) && $result['TableDescription']) {
            return true;
        }
        else {
            return false;
        }
    }

    public function waitForTableCreation($tableName, $timeout = 60, $pollInterval = 1, $blocking = true)
    {
        $args = [
            'TableName' => $tableName,
            '@waiter'   => [
                'delay'       => $pollInterval,
                'maxAttempts' => ceil($timeout / $pollInterval),
            ],
        ];

        $promise = $this->db->getWaiter('TableExists', $args)->promise();

        if ($blocking) {
            $promise->wait();

            return true;
        }
        else {
            return $promise;
        }
    }

    public function waitForTableDeletion($tableName, $timeout = 60, $pollInterval = 1, $blocking = true)
    {
        $args = [
            'TableName' => $tableName,
            '@waiter'   => [
                'delay'       => $pollInterval,
                'maxAttempts' => ceil($timeout / $pollInterval),
            ],
        ];

        $promise = $this->db->getWaiter('TableNotExists', $args)->promise();

        if ($blocking) {
            $promise->wait();

            return true;
        }
        else {
            return $promise;
        }
    }

    public function waitForTablesToBeFullyReady($tableNames, $timeout = 60, $interval = 2)
    {
        $started = time();
        if (is_string($tableNames)) {
            $tableNames = [$tableNames];
        }
        while ($tableNames) {
            $promises = [];
            foreach ($tableNames as $tableName) {
                $args    = [
                    "TableName" => $tableName,
                ];
                $promise = $this->db->describeTableAsync($args);
                $promise->then(
                    function (Result $result) use (&$tableNames, $tableName) {
                        if ($result['Table']['TableStatus'] == "ACTIVE") {
                            if (isset($result['Table']['GlobalSecondaryIndexes'])
                                && $result['Table']['GlobalSecondaryIndexes']
                            ) {
                                foreach ($result['Table']['GlobalSecondaryIndexes'] as $gsi) {
                                    if ($gsi['IndexStatus'] != "ACTIVE") {

                                        return;
                                    }
                                }
                            }

                            $k = array_search($tableName, $tableNames);
                            array_splice($tableNames, $k, 1);
                        }
                    }
                );
                $promises[] = $promise;
            }

            \GuzzleHttp\Promise\all($promises)->wait();
            if ($tableNames) {
                if (time() - $started > $timeout) {
                    $this->logger->warning(
                        "Timed out, some tables are still in unready state",
                        $tableNames
                    );

                    return false;
                }
                sleep($interval);
            }
        }

        return true;
    }
}
