<?php
namespace McnHealthcare\ODM\Dynamodb\Query;

/**
 * Interface QueryExprFactoryInterface
 * Query experssion factory interface.
 */
interface QueryExprFactoryInterface
{
    /**
     * Creates expression elements in a static context.
     *
     * @param string $name Method name.
     * @param array $args Method arguments.
     *
     * @return null|QueryExprInterface.
     */
    public static function __callStatic($name, $args): ?QueryExprInterface;

    /**
     * Creates expression elements in an object context.
     *
     * @param string $name Method name.
     * @param array $args Method arguments.
     *
     * @return null|QueryExprInterface.
     */
    public function __call($name, $args): ?QueryExprInterface;
}
