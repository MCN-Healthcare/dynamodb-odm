<?php
namespace McnHealthcare\ODM\Dynamodb\Query;

/**
 * Class QueryExprFactory
 * Query experssion factory.
 */
class QueryExprFactory implements QueryExprFactoryInterface
{
    /**
     * Map of method names to expression classes.
     *
     * @var array
     */
    protected static $expressionMap = [
        'and'     => AndExpr::class,
        'between' => BetweenExpr::class,
        'eq'      => EqualExpr::class,
        'gt'      => GreaterThanExpr::class,
        'gte'     => GreaterThanOrEqualExpr::class,
        'lt'      => LessThanExpr::class,
        'lte'     => LessThanOrEqualExpr::class,
        'or'      => OrExpr::class,
        'size'    => SizeExpr::class,
    ];

    /**
     * Creates expression elements in a static context.
     *
     * @param string $name Method name.
     * @param array $args Method arguments.
     *
     * @return null|QueryExprInterface.
     */
    public static function __callStatic($name, $args): ?QueryExprInterface
    {
        if (isset(static::$expressionMap[$name])) {
            $ecn = static::$expressionMap[$name];

            return new $ecn(...$args);
        }

        return null;
    }

    /**
     * Creates expression elements in an object context.
     *
     * @param string $name Method name.
     * @param array $args Method arguments.
     *
     * @return null|QueryExprInterface.
     */
    public function __call($name, $args): ?QueryExprInterface
    {
        if (isset(static::$expressionMap[$name])) {
            $ecn = static::$expressionMap[$name];

            return new $ecn(...$args);
        }

        return null;
    }
}
