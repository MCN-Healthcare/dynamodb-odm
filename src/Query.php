<?php
namespace McnHealthcare\ODM\Dynamodb;

use Psr\Log\LoggerInterface;
use McnHealthcare\ODM\Dynamodb\Query\QueryExprInterface;
use McnHealthcare\ODM\Dynamodb\Query\QueryExprFactoryInterface;
use McnHealthcare\ODM\Dynamodb\Query\QueryExprFactory;
use McnHealthcare\ODM\Dynamodb\Annotations\Index;

/**
 * Class Query
 * Builds a dynamo db query/scan.
 */
class Query implements QueryInterface
{
    /**
     * Data in ascending order flag.
     *
     * @var bool
     */
    protected $ascendingOrder = true;

    /**
     * Data consistency flag.
     *
     * @var bool
     */
    protected $consistentRead = false;

    /**
     * Query expression factory.
     *
     * @var QueryExprFactoryInterface
     */
    protected $expr;

    /**
     * Filter criteria (scan).
     *
     * @var string
     */
    protected $filterQuery = '';

    /**
     * What index to use for a query.
     * true means use primary index.
     *
     * @var bool|string
     */
    protected $indexName = true;

    /**
     * Criteria associated with index.
     *
     * @var array
     */
    protected $keyQuery = '';

    /**
     * Root manager iterface for dynamodb repositories and items.
     *
     * @var ItemManagerInterface
     */
    protected $itemClass;

    /**
     * Root manager iterface for dynamodb repositories and items.
     *
     * @var ItemManagerInterface
     */
    protected $itemManager;

    /**
     * Repository for itemClass.
     *
     * @var ItemRepositoryInterface
     */
    protected $itemRepository;

    /**
     * Max items per query.
     *
     * @var int
     */
    protected $limit = 100;

    /**
     * Reports exceptions and errors.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Query or scan operation.
     *
     * @var string
     */
    protected $op;

    /**
     * Map of criteria parameters.
     *
     * @var array
     */
    protected $params = [];

    /**
     * Result set.
     *
     * @var array of ItemInterface objects.
     */
    protected $results = [];

    /**
     * Collection of where expressions.
     *
     * @var array of QueryExpr
     */
    protected $where = [];

    /**
     * Initialize instance.
     *
     * @param LoggerInterface $logger For writing log entries.
     * @param ItemManagerInterface $itemManager
     * For odm metadata and operations.
     * @param QueryExprFactoryInterface $expr Query expression factory.
     */
    public function __construct(
        LoggerInterface $logger,
        ItemManagerInterface $itemManager,
        QueryExprFactoryInterface $expr = null
    ) {
        $this->logger = $logger;
        $this->itemManager = $itemManager;
        $this->expr = $expr ?? new QueryExprFactory();
    }

    /**
     * {@inheritdoc}
     */
    public function execute(array $params = []): QueryInterface
    {
        if (0 < count($params)) {
            $this->params = $params;
        }
        if (static::QUERY_OP == $this->op) {
            return $this->collectQueryResults();
        }
        return $this->collectScanResults();
    }

    /**
     * {@inheritdoc}
     */
    public function expr(): QueryExprFactoryInterface
    {
        return $this->expr;
    }

    /**
     * {@inheritdoc}
     */
    public function from(string $itemClass): QueryInterface
    {
        $this->itemClass = $itemClass;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * {@inheritdoc}
     */
    public function limit(int $limit): QueryInterface
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function parameter(string $name, $value): QueryInterface
    {
        $this->params[$name] = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function prepare(): QueryInterface
    {
        $this->itemRepository = $this->itemManager->getRepository(
            $this->itemClass
        );
        $this->indexName = true;
        $this->keyQuery = '';
        $this->filterQuery = '';
        $this->op = static::SCAN_OP;
        $expressions = $this->where;
        $this->where = [];
        foreach ($expressions as $expr) {
            $this->bindExpression($expr);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function where(QueryExprInterface $expr): QueryInterface
    {
        $this->where[] = $expr;

        return $this;
    }

    /**
     * Binds a query expression to key/filter query.
     *
     * @param QueryExprInterface $expr Query expression.
     */
    protected function bindExpression(QueryExprInterface $expr)
    {
        if ($this->bindKeyQuery($expr)) {
            $this->op = static::QUERY_OP;

            return;
        }
        $this->bindFilterQuery($expr);
    }

    /**
     * Binds filter query.
     *
     * @param QueryExprInterface $expr Query expression.
     */
    protected function bindFilterQuery(QueryExprInterface $expr)
    {
        $this->filterQuery = $expr->expr();
    }

    /**
     * Binds key query if expression is indexable.
     *
     * @param QueryExprInterface $expr Query expression.
     *
     * @bool
     */
    protected function bindKeyQuery(QueryExprInterface $expr): bool
    {
        $fields = $expr->getFields();
        foreach ($this->getItemIndexes() as $index) {
            if ($this->canBindIndex($index, $fields)) {
                if (0 < strlen($this->keyQuery)) {
                    $this->filterQuery = $this->keyQuery;
                }
                $this->indexName = $index->name;
                $this->keyQuery = $expr->expr();

                return true;
            }
        }

        return false;
    }

    /**
     * Checks an index matches the an expression's fields.
     *
     * @param Index $index The index to evaluate.
     * @param array &$fields an expression's fields map.
     *
     * @bool true if index matched expression fields, false otherwise.
     */
    protected function canBindIndex(Index $index, array &$fields): bool
    {
        if (! isset($fields[$index->hash])) {
            /* expression does not contain the index hash key */
            return false;
        }
        if (! $fields[$index->hash]) {
            /* not an equality test */
            return false;
        }
        $fieldLimit = 1;
        if (0 < strlen($index->range) && isset($fields[$index->range])) {
            $fieldLimit = 2;
        }
        if (count($fields) > $fieldLimit) {
            /* expression contains too many fields */
            return false;
        }

        return true;
    }

    /**
     * Collect query results.
     *
     * @return QueryInterface ($self)
     */
    protected function collectQueryResults(): QueryInterface
    {
        $lastKey = null;
        $this->results = [];
        do {
            array_splice(
                $this->results,
                count($this->results),
                0,
                $this->itemRepository->query(
                    $this->keyQuery,
                    $this->params,
                    $this->indexName,
                    $this->filterQuery,
                    $lastKey,
                    $this->limit,
                    $this->consistentRead,
                    $this->ascendingOrder
                )
            );
        } while ($lastKey);

        return $this;
    }

    /**
     * Collect scan results.
     *
     * @return QueryInterface ($self).
     */
    protected function collectScanResults(): QueryInterface
    {
        $lastKey = null;
        $this->results = [];
        do {
            array_splice(
                $this->results,
                count($this->results),
                0,
                $this->itemRepository->scan(
                    $this->filterQuery,
                    $this->params,
                    $this->indexName,
                    $lastKey,
                    $this->limit,
                    $this->consistentRead,
                    $this->ascendingOrder
                )
            );
        } while ($lastKey);

        return $this;
    }

    /**
     * Gets list of indexes for item.
     *
     * @array
     */
    protected function getItemIndexes(): array
    {
        $itemReflection = $this->itemManager->getItemReflection(
            $this->itemClass
        );
        $indexes = $itemReflection->getItemIndexes();
        return array_merge(
            [$indexes['primary']],
            $indexes['gsi'],
            $indexes['lsi']
        );
    }
}
