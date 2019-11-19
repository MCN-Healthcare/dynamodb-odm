<?php
namespace McnHealthcare\ODM\Dynamodb\Ut\Query;

use McnHealthcare\ODM\Dynamodb\Query\EqualExpr as ClassUnderTest;
use PHPUnit\Framework\TestCase;

/**
 * class EqualExprTest
 * Tests for the EqualExpr class.
 */
class EqualExprTest extends TestCase
{
    /**
     * Tests getFields().
     */
    public function testGetFields()
    {
        $expr = new ClassUnderTest('field', ':param1');
        $expect = [
            'field' => true
        ];
        $this->assertEquals($expect, $expr->getFields());
    }

    /**
     * Tests expr().
     */
    public function testExpr()
    {
        $expr = new ClassUnderTest('field', ':param1');
        $expect = 'field = :param1';
        $this->assertEquals($expect, $expr->expr());
    }
}
