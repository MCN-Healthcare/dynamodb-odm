<?php
namespace McnHealthcare\ODM\Dynamodb\Ut\Query;

use McnHealthcare\ODM\Dynamodb\Query\AndExpr as ClassUnderTest;
use McnHealthcare\ODM\Dynamodb\Query\EqualExpr;
use McnHealthcare\ODM\Dynamodb\Query\GreaterThanExpr;
use PHPUnit\Framework\TestCase;

/**
 * Class AndExprTest
 * Tests for the AndExpr class.
 */
class AndExprTest extends TestCase
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
        $expect = 'field1 = :param1 AND field2 > :param2';
        $this->assertEquals($expect, $expr->expr());
    }
}
