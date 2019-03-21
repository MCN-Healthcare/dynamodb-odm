<?php
/**
 * @author Derek Boerger <derek.boerger@mcnhealthcare.com>
 * @license Copyright 2019 MCN Healthcare
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated
 * documentation files (the "Software"), to deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to the following conditions:
 * The above copyright notice and this permission notice shall be included in all copies or substantial portions of the
 * Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
 * COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
 * OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 */
namespace Oasis\Mlib\ODM\Dynamodb\Entity;

use Oasis\Mlib\ODM\Dynamodb\Annotations\Field;
use Oasis\Mlib\ODM\Dynamodb\Annotations\Index;
use Oasis\Mlib\ODM\Dynamodb\Annotations\Item;

/**
 * Class ActivityLog
 *
 * @package Oasis\Mlib\ODM\Dynamodb\Entity
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