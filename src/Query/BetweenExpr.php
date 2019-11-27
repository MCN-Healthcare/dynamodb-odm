<?php
namespace McnHealthcare\ODM\Dynamodb\Query;

/**
 * Class BetweenExpr
 * BETWEEN query experssion.
 */
class BetweenExpr implements QueryExprInterface
{
    /**
     * Field name.
     *
     * @var string
     */
    protected $field;

    /**
     * Min value.
     *
     * @var mixed
     */
    protected $min;

    /**
     * Max value.
     *
     * @var mixed
     */
    protected $max;

    /**
     * Initialize instance.
     *
     * @param string $field Field name.
     * @param mixed $min Minimum comparison value.
     * @param mixed $max Maximum comparison value.
     */
    public function __construct(string $field, $min, $max)
    {
        $this->field = $field;
        $this->min = $min;
        $this->max = $max;
    }

    /**
     * Gets unique field names for expresion.
     *
     * @return array
     */
    public function getFields(): array
    {
        /* between can't be used for first key condition */
        return [$this->field => false];
    }

    /**
     * Gets unique field names for expresion.
     *
     * @return array
     */
    public function expr(): string
    {
        return sprintf(
            '%s BETWEEN %s AND %s',
            $this->field,
            $this->min,
            $this->max
        );
    }
}
