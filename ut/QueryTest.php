<?php
namespace AppTests\Bridge\DynamoDbOdm;

use MockFromYaml\MockFromArrayCreatorTrait;
use MockFromYaml\YamlTestCasesReaderTrait;
use Exception;
use PHPUnit\Framework\TestCase;
use McnHealthcare\ODM\Dynamodb\Query as ClassUnderTest;
use McnHealthcare\ODM\Dynamodb\Annotations\Index;

/**
 * Class QueryTest
 * Tests for the Query class.
 */
class QueryTest extends TestCase
{
    use MockFromArrayCreatorTrait;
    use YamlTestCasesReaderTrait;

    /**
     * Gets an array of test cases from a yaml file,
     * where the file has the naming convention: TestClass.method.yaml.
     * The method name is derived from the calling method with the last
     * 8 characters removed (removes "Provider" from method name).
     * yaml file is expected to be in same directory as test class file.
     *
     * @return array
     */
    protected function provideTestCases(): array
    {
        $trace = (new Exception())->getTrace();
        return static::readYamlTestCases(
            sprintf(
                "%s.%s.yaml",
                substr($trace[0]["file"], 0, -4),
                substr($trace[1]['function'], 0, -8)
            )
        );
    }

    /**
     * Data provider for testExecute().
     *
     * @return array
     */
    public function executeProvider(): array
    {
        return $this->provideTestCases();
    }

    /**
     * Builds a domain with indexes.
     *
     * @return array
     */
    protected function buildDomainWithyIndexes(): array
    {
        return [
            'primaryIndex1' => new Index(
                [
                    'hash' => 'pi1HashField',
                    'range' => 'pi1RangeField',
                    'name' =>  'pi1Index',
                ]
            ),
            'gsiIndex1' => new Index(
                [
                    'hash' => 'gsi1HashField',
                    'range' => 'gsi1RangeField',
                    'name' =>  'gsi1Index',
                ]
            ),
            'gsiIndex2' => new Index(
                [
                    'hash' => 'gsi2HashField',
                    'range' => '',
                    'name' =>  'gsi2Index',
                ]
            ),
            'lsiIndex1' => new Index(
                [
                    'hash' => 'lsi1HashField',
                    'range' => 'lsi1RangeField',
                    'name' =>  'lsi1Index',
                ]
            ),
            'lsiIndex2' => new Index(
                [
                    'hash' => 'lsi2HashField',
                    'range' => '',
                    'name' =>  'lsi2Index',
                ]
            ),
        ];
    }

    /**
     * Tests execute().
     *
     * @param array $fixtures Array of mock object descriptors.
     *
     * @dataProvider executeProvider
     */
    public function testExecute(array $fixtures)
    {
        $domain = $this->buildDomainWithyIndexes();
        $this->createMockFixtures($fixtures, $domain);
        $constructorArgs = $domain['container']->get('constructor-args');
        $methodArgs = $domain['container']->get('method-args');
        $fromAgs = $domain['container']->get('from-args');
        $whereArgs = $domain['container']->get('where-args');
        $limitArgs = $domain['container']->get('limit-args');
        $query = new ClassUnderTest(...$constructorArgs);
        $query->from(...$fromAgs);
        $query->where(...$whereArgs);
        $query->limit(...$limitArgs);
        $query->prepare();
        $this->assertInstanceOf(
            ClassUnderTest::class,
            $query->execute(...$methodArgs)
        );
    }

    /**
     * Data provider for testExpr().
     *
     * @return array
     */
    public function exprProvider(): array
    {
        return $this->provideTestCases();
    }

    /**
     * Tests expr().
     *
     * @param array $fixtures Array of mock object descriptors.
     *
     * @dataProvider exprProvider
     */
    public function testExpr(array $fixtures)
    {
        $domain = [];
        $this->createMockFixtures($fixtures, $domain);
        $constructorArgs = $domain['container']->get('constructor-args');
        $expectedType = $domain['container']->get('expected-type');
        $query = new ClassUnderTest(...$constructorArgs);
        $this->assertInstanceOf($expectedType, $query->expr());
    }

    /**
     * Data provider for testFrom().
     *
     * @return array
     */
    public function fromProvider(): array
    {
        return $this->provideTestCases();
    }

    /**
     * Tests from().
     *
     * @param array $fixtures Array of mock object descriptors.
     *
     * @dataProvider fromProvider
     */
    public function testFrom(array $fixtures)
    {
        $domain = [];
        $this->createMockFixtures($fixtures, $domain);
        $constructorArgs = $domain['container']->get('constructor-args');
        $methodArgs = $domain['container']->get('method-args');
        $query = new ClassUnderTest(...$constructorArgs);
        $this->assertInstanceOf(
            ClassUnderTest::class,
            $query->from(...$methodArgs)
        );
    }

    /**
     * Data provider for testGetResults().
     *
     * @return array
     */
    public function getResultsProvider(): array
    {
        return $this->provideTestCases();
    }

    /**
     * Tests getResults().
     *
     * @param array $fixtures Array of mock object descriptors.
     *
     * @dataProvider getResultsProvider
     */
    public function testGetResults(array $fixtures)
    {
        $domain = [];
        $this->createMockFixtures($fixtures, $domain);
        $constructorArgs = $domain['container']->get('constructor-args');
        $methodArgs = $domain['container']->get('method-args');
        $expected = $domain['container']->get('expected');
        $query = new ClassUnderTest(...$constructorArgs);
        $this->assertEquals($expected, $query->getResults(...$methodArgs));
    }

    /**
     * Data provider for testLimit().
     *
     * @return array
     */
    public function limitProvider(): array
    {
        return $this->provideTestCases();
    }

    /**
     * Tests from().
     *
     * @param array $fixtures Array of mock object descriptors.
     *
     * @dataProvider limitProvider
     */
    public function testLimit(array $fixtures)
    {
        $domain = [];
        $this->createMockFixtures($fixtures, $domain);
        $constructorArgs = $domain['container']->get('constructor-args');
        $methodArgs = $domain['container']->get('method-args');
        $query = new ClassUnderTest(...$constructorArgs);
        $this->assertInstanceOf(
            ClassUnderTest::class,
            $query->limit(...$methodArgs)
        );
    }

    /**
     * Data provider for testParameter().
     *
     * @return array
     */
    public function parameterProvider(): array
    {
        return $this->provideTestCases();
    }

    /**
     * Tests parameter().
     *
     * @param array $fixtures Array of mock object descriptors.
     *
     * @dataProvider parameterProvider
     */
    public function testParameter(array $fixtures)
    {
        $domain = [];
        $this->createMockFixtures($fixtures, $domain);
        $constructorArgs = $domain['container']->get('constructor-args');
        $methodArgs = $domain['container']->get('method-args');
        $query = new ClassUnderTest(...$constructorArgs);
        $this->assertInstanceOf(
            ClassUnderTest::class,
            $query->parameter(...$methodArgs)
        );
    }

    /**
     * Data provider for testPrepare().
     *
     * @return array
     */
    public function prepareProvider(): array
    {
        return $this->provideTestCases();
    }

    /**
     * Tests prepare().
     *
     * @param array $fixtures Array of mock object descriptors.
     *
     * @dataProvider prepareProvider
     */
    public function testPrepare(array $fixtures)
    {
        $domain = $this->buildDomainWithyIndexes();
        $this->createMockFixtures($fixtures, $domain);
        $constructorArgs = $domain['container']->get('constructor-args');
        $fromAgs = $domain['container']->get('from-args');
        $whereArgs = $domain['container']->get('where-args');
        $query = new ClassUnderTest(...$constructorArgs);
        $query->from(...$fromAgs);
        $query->where(...$whereArgs);
        $this->assertInstanceOf(
            ClassUnderTest::class,
            $query->prepare()
        );
    }

    /**
     * Data provider for testWhere().
     *
     * @return array
     */
    public function whereProvider(): array
    {
        return $this->provideTestCases();
    }

    /**
     * Tests where().
     *
     * @param array $fixtures Array of mock object descriptors.
     *
     * @dataProvider whereProvider
     */
    public function testWhere(array $fixtures)
    {
        $domain = [];
        $this->createMockFixtures($fixtures, $domain);
        $constructorArgs = $domain['container']->get('constructor-args');
        $methodArgs = $domain['container']->get('method-args');
        $query = new ClassUnderTest(...$constructorArgs);
        $this->assertInstanceOf(
            ClassUnderTest::class,
            $query->where(...$methodArgs)
        );
    }
}
