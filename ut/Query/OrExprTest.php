<?php
namespace McnHealthcare\ODM\Dynamodb\Ut\Query;

use McnHealthcare\ODM\Dynamodb\Query\OrExpr as ClassUnderTest;
use McnHealthcare\ODM\Dynamodb\Query\EqualExpr;
use McnHealthcare\ODM\Dynamodb\Query\GreaterThanExpr;
use PHPUnit\Framework\TestCase;

/**
 * class OrExprTest
 * Tests for the OrExpr class.
 */
class OrExprTest extends TestCase
{
    /**
     * Tests getFields().
     */
    public function testGetFields()
    {
        $left = new EqualExpr('field1', ':param1');
        $right = new GreaterThanExpr('field2', ':param2');
        $expr = new ClassUnderTest($left, $right);
        $expect = [
            'field1' => true,
            'field2' => false
        ];
        $this->assertEquals($expect, $expr->getFields());
    }

    /**
     * Tests expr().
     */
    public function testExpr()
    {
        $left = new EqualExpr('field1', ':param1');
        $right = new GreaterThanExpr('field2', ':param2');
        $expr = new ClassUnderTest($left, $right);
        $expect = 'field1 = :param1 OR field2 > :param2';
        $this->assertEquals($expect, $expr->expr());
    }
}
