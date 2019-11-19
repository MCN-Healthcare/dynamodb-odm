<?php
namespace McnHealthcare\ODM\Dynamodb\Query;

/**
 * Class AndExpr
 * AND query experssion.
 */
class AndExpr implements QueryExprInterface
{
    /**
     * Left expersion.
     *
     * @var QueryExprInterface
     */
    protected $left;

    /**
     * Right expersion.
     *
     * @var QueryExprInterface
     */
    protected $right;

    /**
     * Initialize instance.
     *
     * @param QueryExprInterface $left Left expersion.
     * @param QueryExprInterface $right Right expersion.
     */
    public function __construct(
        QueryExprInterface $left,
        QueryExprInterface $right
    ) {
        $this->left = $left;
        $this->right = $right;
    }

    /**
     * Gets unique field names for expresion.
     *
     * @return array
     */
    public function getFields(): array
    {
        return $this->left->getFields() + $this->right->getFields();
    }

    /**
     * Gets unique field names for expresion.
     *
     * @return array
     */
    public function expr(): string
    {
        return sprintf(
            '%s AND %s',
            $this->left->expr(),
            $this->right->expr()
        );
    }
}
