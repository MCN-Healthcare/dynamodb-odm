<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-19
 * Time: 11:08
 */

use McnHealthcare\ODM\Dynamodb\Console\ConsoleHelper;
use McnHealthcare\ODM\Dynamodb\ItemManager;
use McnHealthcare\ODM\Dynamodb\Ut\UTConfig;
use Symfony\Component\Yaml\Yaml;

// replace with file to your own project bootstrap
require_once __DIR__ . '/ut/bootstrap.php';

// replace with your own mechanism to retrieve the item manager
UTConfig::load();

$im = new ItemManager(
    UTConfig::$dynamodb,
    UTConfig::$tablePrefix,
    __DIR__ . "/ut/cache"
);
$im->addNamespace('McnHealthcare\ODM\Dynamodb\Ut', __DIR__ . "/ut");

return new ConsoleHelper($im);
