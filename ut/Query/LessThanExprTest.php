<?php
namespace McnHealthcare\ODM\Dynamodb\Ut\Query;

use McnHealthcare\ODM\Dynamodb\Query\LessThanExpr as ClassUnderTest;
use PHPUnit\Framework\TestCase;

/**
 * class LessThanExprTest
 * Tests for the LessThanExpr class.
 */
class LessThanExprTest extends TestCase
{
    /**
     * Tests getFields().
     */
    public function testGetFields()
    {
        $expr = new ClassUnderTest('field', ':param1');
        $expect = [
            'field' => false
        ];
        $this->assertEquals($expect, $expr->getFields());
    }

    /**
     * Tests expr().
     */
    public function testExpr()
    {
        $expr = new ClassUnderTest('field', ':param1');
        $expect = 'field < :param1';
        $this->assertEquals($expect, $expr->expr());
    }
}
