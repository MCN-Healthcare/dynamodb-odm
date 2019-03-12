<?php
/**
 * Created by PhpStorm.
 * User: derek
 * Date: 2019-03-01
 * Time: 11:22
 */

namespace Oasis\Mlib\ODM\Dynamodb\Ut;

use Oasis\Mlib\ODM\Dynamodb\Annotations\Field;
use Oasis\Mlib\ODM\Dynamodb\Annotations\Index;
use Oasis\Mlib\ODM\Dynamodb\Annotations\Item;

/**
 * Class ActivityLog
 *
 * @Item(
 *     table="activity-log",
 *     primaryIndex=@Index(hash="id"),
 *     globalSecondaryIndices={
 *          {"loggedTable", "changedDateTime"},
 *          {"changedBy", "changedDateTime"}
 *      }
 * )
 * @package Oasis\Mlib\ODM\Dynamodb\Ut
 */
class ActivityLog
{
    /**
     * @var int
     * @Field(type="number", name="id")
     */
    protected $id = 0;

    /**
     * @var
     * @Field(type="string", name="loggedTable")
     */
    protected $loggedTable;

    /**
     * @var
     * @Field(type="string", name="changedBy")
     */
    protected $changedBy;

    /**
     * @var
     * @Field(type="number", name="changedDateTime")
     */
    protected $changedDateTime;

    /**
     * @var
     * @Field(type="map", name="previousValues")
     */
    protected $previousValues;

    /**
     * @var
     * @Field(type="map", name="changedToValues")
     */
    protected $changedToValues;



    /**
     * @return string
     */
    public function getLoggedTable(): string
    {
        return $this->loggedTable;
    }

    /**
     * @param string $loggedTable
     */
    public function setLoggedTable(string $loggedTable): void
    {
        $this->loggedTable = $loggedTable;
    }

    /**
     * @return string
     */
    public function getChangedBy(): string
    {
        return $this->changedBy;
    }

    /**
     * @param string $changedBy
     */
    public function setChangedBy(string $changedBy): void
    {
        $this->changedBy = $changedBy;
    }

    /**
     * @return int
     */
    public function getChangedDateTime(): int
    {
        return $this->changedDateTime;
    }

    /**
     * @param int $changedDateTime
     */
    public function setChangedDateTime(int $changedDateTime): void
    {
        $this->changedDateTime = $changedDateTime;
    }

    /**
     * @return array
     */
    public function getPreviousValues(): array
    {
        return $this->previousValues;
    }

    /**
     * @param array $previousValues
     */
    public function setPreviousValues(array $previousValues): void
    {
        $this->previousValues = $previousValues;
    }

    /**
     * @return array
     */
    public function ßgetChangedToValues(): array
    {
        return $this->changedToValues;
    }

    /**
     * @param array $changedToValues
     */
    public function setChangedToValues(array $changedToValues): void
    {
        $this->changedToValues = $changedToValues;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id): void
    {
        $this->id = $id;
    }

}