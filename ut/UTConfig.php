<?php

namespace McnHealthcare\ODM\Dynamodb\Ut;

use Symfony\Component\Yaml\Yaml;
use Aws\DynamoDb\DynamoDbClient;

/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-09
 * Time: 10:33
 */
class UTConfig
{
    public static $dynamodbConfig = [];
    public static $dynamodb = [];
    public static $tablePrefix    = 'odm-test-';
    
    public static function load()
    {
        $file = __DIR__ . "/ut.yml";
        $yml  = Yaml::parse(file_get_contents($file));
        self::$dynamodbConfig = $yml['dynamodb'];
        self::$tablePrefix    = $yml['prefix'];
        $outer = $yml['dynamodb']['McnHealthcare\ODM\Dynamodb\ItemManager'];
        $cfg = $outer['arguments']['$dynamodbConfig'];
        self::$dynamodb = new DynamodbClient($cfg);
    }
}
