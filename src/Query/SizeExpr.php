<?php
namespace McnHealthcare\ODM\Dynamodb\Query;

/**
 * Class SizeExpr
 * SIZE query experssion.
 */
class SizeExpr implements QueryExprInterface
{
    /**
     * Sub expression.
     *
     * @var QueryExprInterface
     */
    protected $sub;

    /**
     * Initialize instance.
     *
     * @param QueryExprInterface $sub Sub expression.
     */
    public function __construct(
        QueryExprInterface $sub
    ) {
        $this->sub = $sub;
    }

    /**
     * Gets unique field names for expresion.
     *
     * @return array
     */
    public function getFields(): array
    {
        return $this->sub->getFields();
    }

    /**
     * Gets unique field names for expresion.
     *
     * @return array
     */
    public function expr(): string
    {
        $old = array_keys($this->sub->getFields())[0];
        $new = sprintf('size(%s)', $old);
        $data = str_replace($old, $new, $this->sub->expr());

        return $data;
    }
}
