<?php
namespace McnHealthcare\ODM\Dynamodb;

/**
 * Interface ActivityLoggingDetailsInterface
 * Public API for activity logging details.
 *
 * #notes Docblock brief descriptions may be incorrect, as original authors faild
 * to provide useful documentation in the docblocks.
 */
interface ActivityLoggingDetailsInterface
{
    /**
     * Undocumented.
     *
     * @return mixed
     */
    public function getLoggedTable();

    /**
     * Undocumented.
     *
     * @param $tableName
     */
    public function setLoggedTable($tableName): void;

    /**
     * Undocumented.
     *
     * @return mixed
     */
    public function getChangedBy();

    /**
     * Undocumented.
     *
     * @param mixed $changedBy
     */
    public function setChangedBy($changedBy): void;

    /**
     * Undocumented.
     *
     * @return int
     */
    public function getOffset(): int;

    /**
     * Undocumented.
     *
     * @param int $offset
     */
    public function setOffset(int $offset): void;

    /**
     * Undocumented.
     *
     * @return string
     */
    public function getLogTableName();
}
