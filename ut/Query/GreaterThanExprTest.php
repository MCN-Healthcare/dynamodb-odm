<?php
namespace McnHealthcare\ODM\Dynamodb\Ut\Query;

use McnHealthcare\ODM\Dynamodb\Query\GreaterThanExpr as ClassUnderTest;
use PHPUnit\Framework\TestCase;

/**
 * class GreaterThanExprTest
 * Tests for the GreaterThanExpr class.
 */
class GreaterThanExprTest extends TestCase
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
        $expect = 'field > :param1';
        $this->assertEquals($expect, $expr->expr());
    }
}
