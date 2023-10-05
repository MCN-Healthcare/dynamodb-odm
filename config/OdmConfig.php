<?php

namespace McnHealthcare\ODM\Dynamodb\config;

use Symfony\Component\Yaml\Yaml;
use Aws\DynamoDb\DynamoDbClient;

/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-09
 * Time: 10:33
 */
class OdmConfig
{
    protected static $config = [];

    /**
     * Loads config from yaml file.
     */
    public static function load()
    {
        $file = __DIR__ . "/odm-config.yml";
        $yml  = Yaml::parse(file_get_contents($file));
        foreach ($yml['odm'] as $name => $value) {
            static::$config[$name] = static::createObject($value);
        }
    }

    /**
     * Gets element from configuration.
     *
     * @param string $name Top level element key name.
     *
     * @return object
     */
    public static function get(string $name): object
    {
        return static::$config[$name];
    }

    /**
     * Cretes a config object from a definition array.
     *
     * @param array $definition Definition of config object.
     *
     * @return object
     */
    protected static function createObject(array $definition): object
    {
        $className = $definition['class'];
        $args = static::resolveArgs($definition);
        $obj = new $className(...$args);
        static::resolveCalls($obj, $definition);

        return $obj;
    }

    /**
     * Resolves method call arguments.
     *
     * @param array $definition Configuration section that might contain the arguments key.
     *
     * @return array
     */
    protected static function resolveArgs(array $definition): array
    {
        if (array_key_exists('arguments', $definition)) {
            foreach ($definition['arguments'] as $arg) {
                if (is_string($arg) && '@' === substr($arg, 0, 1)) {
                    $args[] = static::get(substr($arg, 1));
                } else {
                    $args[] = $arg;
                }
            }
        }

        return $args;
    }

    /**
     * Resolves initialization calls in configuration objects.
     *
     * @param object $obj Configuration object being initialized.
     * @param array $definition Configuration section that might contain the calls key.
     */
    protected static function resolveCalls(object $obj, array $definition)
    {
        if (array_key_exists('calls', $definition)) {
            foreach ($definition['calls'] as $call) {
                $method = $call['method'];
                call_user_func_array([$obj, $method], static::resolveArgs($call));
            }
        }
    }
}
