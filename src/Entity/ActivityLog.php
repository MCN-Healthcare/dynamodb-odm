<?php
/*
 * This file is part AWS DynamoDB ODM.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace McnHealthcare\ODM\Dynamodb\Entity;

use McnHealthcare\ODM\Dynamodb\Annotations\Field;
use McnHealthcare\ODM\Dynamodb\Annotations\Index;
use McnHealthcare\ODM\Dynamodb\Annotations\Item;

/**
 * Class ActivityLog
 *
 * @package McnHealthcare\ODM\Dynamodb\Entity
 * @Item(
 *     table="activity-log",
 *     primaryIndex=@Index(hash="id"),
 *     globalSecondaryIndices={
 *          {"loggedTable", "changedDateTime"},
 *          {"changedBy", "changedDateTime"}
 *      }
 * )
 */
class ActivityLog
{
    /**
     * @var int
     * @Field(type="number", name="id")
     */
    protected $id = 0;

    /**
     * @var string
     * @Field(type="string", name="loggedTable")
     * @ Assert\NotBlank(message="Logged Table cannot be blank.")
     */
    protected $loggedTable;

    /**
     * @var string
     * @Field(type="string", name="changedBy")
     * @ Assert\NotBlank(message="Changed By cannot be blank.")
     */
    protected $changedBy;

    /**
     * @var int
     * @Field(type="number", name="changedDateTime")
     * @ Assert\NotBlank(message="Changed Date Time cannot be blank.")
     */
    protected $changedDateTime;

    /**
     * @var array
     * @Field(type="map", name="previousValues")
     */
    protected $previousValues;

    /**
     * @var array
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
    public function getChangedToValues(): array
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
