<?php
namespace McnHealthcare\ODM\Dynamodb\Query;

/**
 * Class EqualExpr
 * Equality query expression.
 */
class EqualExpr implements QueryExprInterface
{
    /**
     * Field name.
     *
     * @var string
     */
    protected $field;

    /**
     * Comparison value.
     *
     * @var mixed
     */
    protected $value;

    /**
     * Initialize instance.
     *
     * @param string $field Field name.
     * @param mixed $value Comparison value.
     */
    public function __construct(string $field, $value)
    {
        $this->field = $field;
        $this->value = $value;
    }

    /**
     * Gets unique field names for expresion.
     *
     * @return array
     */
    public function getFields(): array
    {
        return [$this->field => true];
    }

    /**
     * Gets unique field names for expresion.
     *
     * @return array
     */
    public function expr(): string
    {
        return sprintf('%s = %s', $this->field, $this->value);
    }
}
