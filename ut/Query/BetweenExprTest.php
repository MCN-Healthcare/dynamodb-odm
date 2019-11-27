<?php
namespace McnHealthcare\ODM\Dynamodb\Ut\Query;

use McnHealthcare\ODM\Dynamodb\Query\BetweenExpr as ClassUnderTest;
use PHPUnit\Framework\TestCase;

/**
 * class BetweenExprTest
 * Tests for the BetweenExpr class.
 */
class BetweenExprTest extends TestCase
{
    /**
     * Tests getFields().
     */
    public function testGetFields()
    {
        $expr = new ClassUnderTest('field', ':param1', ':param2');
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
        $expr = new ClassUnderTest('field', ':param1', ':param2');
        $expect = 'field BETWEEN :param1 AND :param2';
        $this->assertEquals($expect, $expr->expr());
    }
}
