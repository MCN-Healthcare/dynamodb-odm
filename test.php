#! /usr/bin/env php
<?php
/*
 * This file is part AWS DynamoDB ODM.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


use McnHealthcare\ODM\Dynamodb\ItemManager;
use McnHealthcare\ODM\Dynamodb\Ut\CasDemoUser;
use McnHealthcare\ODM\Dynamodb\Ut\UTConfig;

require_once __DIR__ . "/vendor/autoload.php";

$client = new \Aws\DynamoDb\DynamoDbClient(
    [
        'version'     => 'latest',
        'region'      => 'us-west-2',
        'endpoint'    => 'http://localhost:8000',
        'credentials' => [
            'key'    => 'my-access-key-id',
            'secret' => 'my-secret-access-key',
        ]
    ]
);

UTConfig::load();
$im = new ItemManager(
    $client, UTConfig::$tablePrefix, __DIR__ . "/cache", true
);

$user       = new CasDemoUser();
$user->id   = mt_rand();
$user->name = 'John';
$user->ver  = '1';
$im->persist($user);
$im->flush();

//$user = $im->get(CasDemoUser::class, ['id' => 1]);
//$user->name = 'Alice';
//$user->ver = '2';
////sleep(5);
//$im->flush();
