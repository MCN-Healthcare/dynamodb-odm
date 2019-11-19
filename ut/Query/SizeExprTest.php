<?php
namespace McnHealthcare\ODM\Dynamodb\Ut\Query;

use McnHealthcare\ODM\Dynamodb\Query\SizeExpr as ClassUnderTest;
use McnHealthcare\ODM\Dynamodb\Query\EqualExpr;
use PHPUnit\Framework\TestCase;

/**
 * class SizeExprTest
 * Tests for the SizeExpr class.
 */
class SizeExprTest extends TestCase
{
    /**
     * Tests getFields().
     */
    public function testGetFields()
    {
        $inner = new EqualExpr('field1', ':param1');
        $expr = new ClassUnderTest($inner);
        $expect = [
            'field1' => true,
        ];
        $this->assertEquals($expect, $expr->getFields());
    }

    /**
     * Tests expr().
     */
    public function testExpr()
    {
        $inner = new EqualExpr('field1', ':param1');
        $expr = new ClassUnderTest($inner);
        $expect = 'size(field1) = :param1';
        $this->assertEquals($expect, $expr->expr());
    }
}
