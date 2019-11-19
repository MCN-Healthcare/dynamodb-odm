<?php
namespace AppTests\Bridge\DynamoDbOdm\Ut\Query;

use McnHealthcare\ODM\Dynamodb\Query\QueryExprFactory as ClassUnderTest;
use McnHealthcare\ODM\Dynamodb\Query\AndExpr;
use McnHealthcare\ODM\Dynamodb\Query\BetweenExpr;
use McnHealthcare\ODM\Dynamodb\Query\EqualExpr;
use McnHealthcare\ODM\Dynamodb\Query\GreaterThanExpr;
use McnHealthcare\ODM\Dynamodb\Query\GreaterThanOrEqualExpr;
use McnHealthcare\ODM\Dynamodb\Query\LessThanExpr;
use McnHealthcare\ODM\Dynamodb\Query\LessThanOrEqualExpr;
use McnHealthcare\ODM\Dynamodb\Query\OrExpr;
use McnHealthcare\ODM\Dynamodb\Query\SizeExpr;
use PHPUnit\Framework\TestCase;

/**
 * class QueryExprFactoryTest
 * Tests for the QueryExprFactory class.
 */
class QueryExprFactoryTest extends TestCase
{
    /**
     * Tests invalid expression.
     */
    public function testInvalid()
    {
        $this->assertNull(ClassUnderTest::noexpr());
        $expr = new ClassUnderTest();
        $this->assertNull($expr->noexpr());
    }

    /**
     * Tests and().
     */
    public function testAnd()
    {
        $this->assertInstanceOf(
            AndExpr::class,
            ClassUnderTest::and(
                ClassUnderTest::eq('field1', ':param1'),
                ClassUnderTest::eq('field2', ':param2')
            )
        );
        $expr = new ClassUnderTest();
        $this->assertInstanceOf(
            AndExpr::class,
            $expr->and(
                $expr->eq('field1', ':param1'),
                $expr->eq('field2', ':param2')
            )
        );
    }

    /**
     * Tests between().
     */
    public function testBetween()
    {
        $this->assertInstanceOf(
            BetweenExpr::class,
            ClassUnderTest::between('field', 'param1', ':param2')
        );
    }

    /**
     * Tests eq().
     */
    public function testEq()
    {
        $this->assertInstanceOf(
            EqualExpr::class,
            ClassUnderTest::eq('field', 'param')
        );
    }

    /**
     * Tests gt().
     */
    public function testGt()
    {
        $this->assertInstanceOf(
            GreaterThanExpr::class,
            ClassUnderTest::gt('field', 'param')
        );
    }

    /**
     * Tests gte().
     */
    public function testGte()
    {
        $this->assertInstanceOf(
            GreaterThanOrEqualExpr::class,
            ClassUnderTest::gte('field', 'param')
        );
    }

    /**
     * Tests lt().
     */
    public function testLt()
    {
        $this->assertInstanceOf(
            LessThanExpr::class,
            ClassUnderTest::lt('field', 'param')
        );
    }

    /**
     * Tests lte().
     */
    public function testLte()
    {
        $this->assertInstanceOf(
            LessThanOrEqualExpr::class,
            ClassUnderTest::lte('field', 'param')
        );
    }

    /**
     * Tests or().
     */
    public function testOr()
    {
        $this->assertInstanceOf(
            OrExpr::class,
            ClassUnderTest::or(
                ClassUnderTest::eq('field1', ':param1'),
                ClassUnderTest::eq('field2', ':param2')
            )
        );
    }

    /**
     * Tests size().
     */
    public function testSize()
    {
        $this->assertInstanceOf(
            SizeExpr::class,
            ClassUnderTest::size(ClassUnderTest::gt('field', 'param'))
        );
    }
}
