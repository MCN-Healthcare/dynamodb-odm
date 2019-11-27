<?php
namespace McnHealthcare\ODM\Dynamodb;

/**
 * Interface ActivityLoggingDetailsInterface
 * Public API for activity logging details.
 */
interface ActivityLoggingDetailsInterface
{
    /**
     * Gets full name of table being logged.
     *
     * @return null|string
     */
    public function getLoggedTable(): ?string;

    /**
     * Sets table being logged.
     *
     * @param string $tableName Full name of table being logged.
     */
    public function setLoggedTable(string $tableName): void;

    /**
     * Gets identity associated with change.
     *
     * @return null|string
     */
    public function getChangedBy(): ?string;

    /**
     * Sets identity associated with change.
     *
     * @param string $changedBy who/what caused a change.
     */
    public function setChangedBy(string $changedBy): void;

    /**
     * Gets UTC offset in seconds.
     *
     * @return int
     */
    public function getOffset(): int;

    /**
     * Sets UTC offset in seconds.
     *
     * @param int $offset Offset from UTC in seconds.
     */
    public function setOffset(int $offset): void;

    /**
     * Gets full name of table loggs are written to.
     *
     * @return string
     */
    public function getLogTableName(): string;
}
